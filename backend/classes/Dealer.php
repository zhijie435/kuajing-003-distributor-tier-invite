<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/DealerLevel.php';

class Dealer
{
    private $db;
    private $table = 'dealer';
    private $levelModel;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->levelModel = new DealerLevel();
    }

    public function generateDealerNo()
    {
        return 'DL' . date('YmdHis') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function getById($id)
    {
        $sql = "SELECT d.*, dl.level_name, dl.level_code, dl.level_weight, dl.discount_rate, dl.commission_rate
                FROM `{$this->table}` d
                LEFT JOIN `dealer_level` dl ON d.level_id = dl.id
                WHERE d.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }

    public function getByPhone($phone)
    {
        $sql = "SELECT d.*, dl.level_name, dl.level_code, dl.level_weight
                FROM `{$this->table}` d
                LEFT JOIN `dealer_level` dl ON d.level_id = dl.id
                WHERE d.phone = ?";
        return $this->db->fetchOne($sql, [$phone]);
    }

    public function getByDealerNo($dealerNo)
    {
        $sql = "SELECT d.*, dl.level_name, dl.level_code, dl.level_weight
                FROM `{$this->table}` d
                LEFT JOIN `dealer_level` dl ON d.level_id = dl.id
                WHERE d.dealer_no = ?";
        return $this->db->fetchOne($sql, [$dealerNo]);
    }

    public function getList($params = [])
    {
        $where = ['1=1'];
        $bindParams = [];

        if (!empty($params['keyword'])) {
            $where[] = "(d.name LIKE ? OR d.phone LIKE ? OR d.dealer_no LIKE ?)";
            $keyword = "%{$params['keyword']}%";
            $bindParams[] = $keyword;
            $bindParams[] = $keyword;
            $bindParams[] = $keyword;
        }
        if (isset($params['level_id']) && $params['level_id'] !== '') {
            $where[] = "d.level_id = ?";
            $bindParams[] = $params['level_id'];
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = "d.status = ?";
            $bindParams[] = $params['status'];
        }
        if (!empty($params['parent_id'])) {
            $where[] = "d.parent_id = ?";
            $bindParams[] = $params['parent_id'];
        }

        $whereSql = implode(' AND ', $where);
        $countSql = "SELECT COUNT(*) FROM `{$this->table}` d WHERE {$whereSql}";
        $total = $this->db->fetchColumn($countSql, $bindParams);

        $page = isset($params['page']) ? max(1, (int)$params['page']) : 1;
        $pageSize = isset($params['pageSize']) ? min(100, max(1, (int)$params['pageSize'])) : 10;
        $offset = ($page - 1) * $pageSize;

        $sql = "SELECT d.*, dl.level_name, dl.level_code, dl.level_weight
                FROM `{$this->table}` d
                LEFT JOIN `dealer_level` dl ON d.level_id = dl.id
                WHERE {$whereSql}
                ORDER BY d.id DESC
                LIMIT {$offset}, {$pageSize}";
        $list = $this->db->fetchAll($sql, $bindParams);

        return ['list' => $list, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize];
    }

    public function create($data)
    {
        $data['dealer_no'] = $this->generateDealerNo();
        $data['level_id'] = $data['level_id'] ?? 1;
        $data['registered_at'] = date('Y-m-d H:i:s');
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

    public function incrementInviteCount($inviterId)
    {
        $sql = "UPDATE `{$this->table}` SET `total_invite_count` = `total_invite_count` + 1 WHERE `id` = ?";
        return $this->db->query($sql, [$inviterId])->rowCount();
    }

    public function updateTeamCount($dealerId, $count)
    {
        $sql = "UPDATE `{$this->table}` SET `total_team_count` = ? WHERE `id` = ?";
        return $this->db->query($sql, [$count, $dealerId])->rowCount();
    }

    public function updateLevel($dealerId, $levelId)
    {
        return $this->db->update($this->table, ['level_id' => $levelId], '`id` = ?', [$dealerId]);
    }

    public function getInviteChain($dealerId, $direction = 'up')
    {
        $result = [];
        if ($direction === 'up') {
            $currentId = $dealerId;
            $depth = 0;
            while ($currentId && $depth < 20) {
                $sql = "SELECT d.id, d.dealer_no, d.name, d.phone, d.level_id, d.parent_id,
                               dl.level_name, dl.level_code, dl.level_weight
                        FROM `{$this->table}` d
                        LEFT JOIN `dealer_level` dl ON d.level_id = dl.id
                        WHERE d.id = ?";
                $dealer = $this->db->fetchOne($sql, [$currentId]);
                if (!$dealer) break;
                $dealer['_depth'] = $depth;
                $result[] = $dealer;
                $currentId = $dealer['parent_id'];
                $depth++;
            }
        } else {
            $sql = "SELECT d.id, d.dealer_no, d.name, d.phone, d.level_id, d.parent_id,
                           dl.level_name, dl.level_code, dl.level_weight
                    FROM `{$this->table}` d
                    LEFT JOIN `dealer_level` dl ON d.level_id = dl.id
                    WHERE d.parent_id = ?
                    ORDER BY d.id DESC";
            $children = $this->db->fetchAll($sql, [$dealerId]);
            foreach ($children as $child) {
                $child['_children'] = $this->getInviteChain($child['id'], 'down');
                $result[] = $child;
            }
        }
        return $result;
    }

    public function getInviteTree($dealerId, $maxDepth = 5)
    {
        $sql = "SELECT d.id, d.dealer_no, d.name, d.phone, d.level_id, d.parent_id,
                       d.total_invite_count, d.total_team_count,
                       dl.level_name, dl.level_code, dl.level_weight
                FROM `{$this->table}` d
                LEFT JOIN `dealer_level` dl ON d.level_id = dl.id
                WHERE d.id = ?";
        $root = $this->db->fetchOne($sql, [$dealerId]);
        if ($root) {
            $root['_children'] = $this->buildTree($dealerId, 1, $maxDepth);
        }
        return $root;
    }

    private function buildTree($parentId, $currentDepth, $maxDepth)
    {
        if ($currentDepth >= $maxDepth) return [];
        $sql = "SELECT d.id, d.dealer_no, d.name, d.phone, d.level_id, d.parent_id,
                       d.total_invite_count, d.total_team_count,
                       dl.level_name, dl.level_code, dl.level_weight
                FROM `{$this->table}` d
                LEFT JOIN `dealer_level` dl ON d.level_id = dl.id
                WHERE d.parent_id = ?
                ORDER BY d.id DESC";
        $children = $this->db->fetchAll($sql, [$parentId]);
        foreach ($children as &$child) {
            $child['_depth'] = $currentDepth;
            $child['_children'] = $this->buildTree($child['id'], $currentDepth + 1, $maxDepth);
        }
        return $children;
    }

    public function getStats($dealerId = null)
    {
        $where = '';
        $params = [];
        if ($dealerId) {
            $where = "WHERE `id` = ?";
            $params[] = $dealerId;
        }
        $stats = [];
        $stats['total_dealers'] = $this->db->fetchColumn("SELECT COUNT(*) FROM `{$this->table}` {$where}", $params);
        $stats['total_invites'] = $this->db->fetchColumn("SELECT COALESCE(SUM(total_invite_count), 0) FROM `{$this->table}` {$where}", $params);
        $stats['total_amount'] = $this->db->fetchColumn("SELECT COALESCE(SUM(total_order_amount), 0) FROM `{$this->table}` {$where}", $params);
        $stats['total_commission'] = $this->db->fetchColumn("SELECT COALESCE(SUM(total_commission), 0) FROM `{$this->table}` {$where}", $params);

        $levelStatsSql = "SELECT dl.level_name, dl.level_code, COUNT(d.id) as count
                          FROM `dealer_level` dl
                          LEFT JOIN `{$this->table}` d ON d.level_id = dl.id" . ($dealerId ? " AND d.id = ?" : "") . "
                          GROUP BY dl.id, dl.level_name, dl.level_code
                          ORDER BY dl.level_weight ASC";
        $stats['level_distribution'] = $this->db->fetchAll($levelStatsSql, $dealerId ? [$dealerId] : []);

        return $stats;
    }
}
