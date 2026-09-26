<?php
declare(strict_types=1);

class DashboardService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function summary(): array
    {
        return [
            'inventory'    => $this->inventory_counts(),
            'contracts'    => $this->contract_counts(),
            'financial'    => $this->financials(),
            'sales'        => $this->sales_report(),
            'generated_at' => date('c'),
        ];
    }

    private function inventory_counts(): array
    {
        $stmt = $this->db->query(
            "SELECT status, COUNT(*) AS total FROM inventory GROUP BY status"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        return [
            'total_items' => (int) array_sum($rows),
            'available'   => (int) ($rows['Available'] ?? 0),
            'rented'      => (int) ($rows['Rented'] ?? 0),
            'maintenance' => (int) ($rows['Maintenance'] ?? 0),
        ];
    }

    private function contract_counts(): array
    {
        $active = $this->db->query(
            "SELECT COUNT(*) FROM rental_contracts WHERE status = 'Active' AND due_date >= CURDATE()"
        )->fetchColumn();

        $overdue = $this->db->query(
            "SELECT COUNT(*) FROM rental_contracts WHERE status != 'Returned' AND due_date < CURDATE()"
        )->fetchColumn();

        $returned_today = $this->db->query(
            "SELECT COUNT(*) FROM rental_contracts WHERE status = 'Returned' AND return_date = CURDATE()"
        )->fetchColumn();

        return [
            'active_on_time' => (int) $active,
            'overdue'        => (int) $overdue,
            'returned_today' => (int) $returned_today,
        ];
    }

    private function financials(): array
    {
        $deposits_held = $this->db->query(
            "SELECT COALESCE(SUM(deposit_amount), 0) FROM rental_contracts WHERE status != 'Returned'"
        )->fetchColumn();

        $late_fee_exposure = $this->db->query(
            "SELECT COALESCE(SUM(ci.daily_rate_at_checkout * DATEDIFF(CURDATE(), rc.due_date)), 0)
             FROM rental_contracts rc
             JOIN contract_items ci ON ci.contract_id = rc.id
             WHERE rc.status != 'Returned' AND rc.due_date < CURDATE() AND ci.returned = 0"
        )->fetchColumn();

        return [
            'deposits_held'     => round((float) $deposits_held, 2),
            'late_fee_exposure' => round((float) $late_fee_exposure, 2),
        ];
    }

    private function sales_report(): array
    {
        $row = $this->db->query(
            "SELECT
                COUNT(DISTINCT rc.id) AS completed_rentals,
                COALESCE(SUM(
                    ci.daily_rate_at_checkout * GREATEST(DATEDIFF(rc.return_date, rc.checkout_date), 1)
                ), 0) AS base_revenue,
                COALESCE(SUM(
                    CASE WHEN rc.return_date > rc.due_date
                         THEN ci.daily_rate_at_checkout * DATEDIFF(rc.return_date, rc.due_date)
                         ELSE 0 END
                ), 0) AS late_fees_collected
             FROM contract_items ci
             JOIN rental_contracts rc ON rc.id = ci.contract_id
             WHERE ci.returned = 1 AND rc.return_date IS NOT NULL"
        )->fetch();

        $completed    = (int) ($row['completed_rentals'] ?? 0);
        $base_revenue = (float) ($row['base_revenue'] ?? 0);
        $late_fees    = (float) ($row['late_fees_collected'] ?? 0);
        $total        = $base_revenue + $late_fees;

        $top_item = $this->db->query(
            "SELECT inv.item_name, COUNT(*) AS times_rented
             FROM contract_items ci
             JOIN inventory inv ON inv.id = ci.inventory_id
             GROUP BY ci.inventory_id, inv.item_name
             ORDER BY times_rented DESC
             LIMIT 1"
        )->fetch();

        return [
            'completed_rentals'   => $completed,
            'base_rental_revenue' => round($base_revenue, 2),
            'late_fees_collected' => round($late_fees, 2),
            'total_revenue'       => round($total, 2),
            'average_sale_value'  => $completed > 0 ? round($total / $completed, 2) : 0.0,
            'top_item'            => $top_item['item_name'] ?? null,
            'top_item_rentals'    => (int) ($top_item['times_rented'] ?? 0),
        ];
    }
}