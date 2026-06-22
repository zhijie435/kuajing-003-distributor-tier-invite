<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Response.php';

class DealerLevel
{
    private $db;
    private $table = 'dealer_level';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAll($status = null)
    {
        $sql = "SELECT * FROM `{$this->table}`";
        $params = [];
        if ($status !== null) {
            $sql .= " WHERE `status` = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY `level_weight` ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function getById($id)
    {
        return $this->db->fetchOne("SELECT * FROM `{$this->table}` WHERE `id` = ?", [$id]);
    }

    public function getByCode($code)
    {
        return $this->db->fetchOne("SELECT * FROM `{$this->table}` WHERE `level_code` = ?", [$code]);
    }

    public function getNextLevel($currentWeight)
    {
        return $this->db->fetchOne(
            "SELECT * FROM `{$this->table}` WHERE `level_weight` > ? AND `status` = 1 ORDER BY `level_weight` ASC LIMIT 1",
            [$currentWeight]
        );
    }

    public function getHighestLevel()
    {
        return $this->db->fetchOne("SELECT * FROM `{$this->table}` WHERE `status` = 1 ORDER BY `level_weight` DESC LIMIT 1");
    }

    public function create($data)
    {
        return $this->db->insert($this->table, $data);
    }

    public function update($id, $data)
    {
        return $this->db->update($this->table, $data, '`id` = ?', [$id]);
    }

    public function delete($id)
    {
        return $this->db->delete($this->table, '`id` = ?', [$id]);
    }

    public function checkUpgradeCondition($dealer, $targetLevel)
    {
        return $dealer['total_invite_count'] >= $targetLevel['min_invite_count']
            || $dealer['total_order_amount'] >= $targetLevel['min_order_amount'];
    }
}
