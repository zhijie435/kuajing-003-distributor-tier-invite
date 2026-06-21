# 内容审核标注平台 - 经销商等级邀请码流转系统

基于 **Vue 3 + Laravel 10** 开发的经销商体系管理系统，实现了完整的邀请码生成与流转、多级邀请链路追踪、经销商等级晋升和升级记录管理。

---

## 功能模块

### 1. 数据概览（Dashboard）
- 用户总数、活跃用户、总业绩、总邀请数等核心指标
- 邀请码使用情况统计（有效/已用完/已过期/使用率）
- 等级分布饼图（ECharts）
- 升级类型分布饼图
- 待处理升级奖励提示

### 2. 用户管理
- 用户列表查询（按等级/邀请人/状态/关键字）
- 用户详情：等级信息、邀请链路祖先、团队统计
- 等级晋升进度可视化（业绩+邀请人数双维度进度条）
- 升级时间线展示
- 新增用户、添加业绩、查看邀请链路

### 3. 等级管理
- 5档经销商等级配置（普通/银牌/金牌/铂金/钻石）
- 升级条件：最低业绩 + 最低邀请人数
- 佣金比例、升级奖励金额配置
- 等级特权JSON配置
- 启用/禁用、删除保护

### 4. 邀请码管理
- **邀请码生命周期**：生成 → 激活 → 使用 → 用完/过期
- 单个/批量生成邀请码（最多100个/批）
- 绑定目标等级（使用邀请码注册自动获得对应等级）
- 邀请奖励（邀请人）和新人奖励（被邀请人）双奖励配置
- 邀请码使用验证（前端实时校验可用性）
- 使用邀请码：校验 → 建立邀请关系 → 自动升级 → 标记使用
- 查看详情、启停、删除

### 5. 邀请链路
- **树状图可视化**（ECharts Tree），支持2-5层深度
- 用户选择器快速定位根节点
- 按深度分布柱状图
- 链路统计：直接/间接邀请、团队总业绩、累计佣金
- 邀请链路上溯（展示祖先链路 L1/L2/L3...）
- 手动建立邀请关系

### 6. 升级记录
- 完整升级历史（自动/手动/邀请码/后台调整 4种类型）
- 升级趋势折线图（按月）
- 各等级升级统计
- **自动升级检查**：根据业绩+邀请数批量判定升级（支持预检查DryRun）
- 手动升级（不校验条件，直接变更等级）
- 升级奖励发放（单笔/批量/全部一键发放）
- 升级详情抽屉

---

## 技术架构

```
003-内容审核标注平台/
├── backend/                        # Laravel 10 后端
│   ├── app/
│   │   ├── Models/
│   │   │   ├── BaseModel.php       # 基础模型（软删除等）
│   │   │   ├── DealerLevel.php     # 经销商等级模型
│   │   │   ├── User.php            # 用户模型（含链路/树构建）
│   │   │   ├── InviteCode.php      # 邀请码模型（生成/使用/校验）
│   │   │   ├── InviteChain.php     # 邀请链路模型（祖先链自动创建）
│   │   │   └── UpgradeRecord.php   # 升级记录模型（事务升级）
│   │   ├── Http/Controllers/Api/
│   │   │   ├── DealerLevelController.php
│   │   │   ├── UserController.php
│   │   │   ├── InviteCodeController.php
│   │   │   ├── InviteChainController.php
│   │   │   └── UpgradeRecordController.php
│   │   └── Traits/ApiResponse.php  # 统一响应Trait
│   ├── config/
│   │   ├── app.php                 # 应用配置（含邀请码/等级配置）
│   │   ├── auth.php
│   │   └── database.php
│   ├── database/
│   │   └── migrations/             # 5个数据表迁移文件
│   ├── routes/api.php              # API路由（完整CRUD）
│   ├── public/index.php
│   ├── composer.json
│   └── .env.example
│
├── frontend/                       # Vue 3 前端
│   ├── src/
│   │   ├── views/
│   │   │   ├── Dashboard.vue       # 数据概览
│   │   │   ├── Users.vue           # 用户管理
│   │   │   ├── UserDetail.vue      # 用户详情 + 升级进度
│   │   │   ├── DealerLevels.vue    # 等级管理
│   │   │   ├── InviteCodes.vue     # 邀请码管理
│   │   │   ├── InviteChains.vue    # 邀请链路可视化
│   │   │   └── UpgradeRecords.vue  # 升级记录
│   │   ├── api/index.js            # API 接口封装（6模块）
│   │   ├── router/index.js         # 路由配置
│   │   ├── utils/request.js        # Axios 拦截器封装
│   │   ├── styles/main.scss        # 全局样式
│   │   ├── App.vue                 # 侧边栏布局
│   │   └── main.js
│   ├── index.html
│   ├── vite.config.js              # Vite 配置（含API代理）
│   └── package.json
│
└── database/
    └── init.sql                    # 完整数据库初始化脚本（含测试数据）
```

---

## 数据库设计

### 1. `dealer_levels` 经销商等级表
| 字段 | 类型 | 说明 |
|------|------|------|
| `level` | INT UNIQUE | 等级权重（越大越高，1~N） |
| `min_achievement` | DECIMAL | 升级所需业绩 |
| `min_invite_count` | INT | 升级所需邀请数 |
| `commission_rate` | DECIMAL | 佣金比例% |
| `reward_bonus` | DECIMAL | 升级奖励金额 |
| `privileges` | JSON | 等级特权配置数组 |

### 2. `users` 用户表
| 字段 | 类型 | 说明 |
|------|------|------|
| `dealer_level_id` | FK | 当前等级 |
| `inviter_id` | FK | **直接**邀请人（自关联） |
| `invite_path` | VARCHAR | 完整邀请路径（`ID1-ID2-ID3-...`） |
| `invite_depth` | INT | 邀请深度（顶级用户=0） |
| `total_achievement` | DECIMAL | 累计业绩 |
| `total_invite_count` | INT | 累计邀请人数 |

### 3. `invite_codes` 邀请码表
| 字段 | 类型 | 说明 |
|------|------|------|
| `code` | VARCHAR(32) UNIQUE | 大写字母+数字 8位（排除易混字符） |
| `owner_id` | FK | 邀请码拥有者 |
| `target_dealer_level_id` | FK | 绑定等级（使用即获得） |
| `max_uses` / `used_count` | INT | 使用次数控制 |
| `reward_amount` | DECIMAL | 邀请人奖励 |
| `new_user_bonus` | DECIMAL | 被邀请人奖励 |
| `status` | TINYINT | 0禁用/1正常/2已用完/3已过期 |

### 4. `invite_chains` 邀请链路表
> **关键设计**：当建立一个直接邀请关系时，**自动为该邀请人的所有祖先各创建一条深度递增的间接链路记录**，方便快速查询任意用户的所有下级（不限层级）和按深度统计。

| 字段 | 类型 | 说明 |
|------|------|------|
| `inviter_id` / `invitee_id` | FK | 唯一约束组合 |
| `depth` | INT | 深度：1=直接邀请 2+=间接 |
| `commission_rate` | DECIMAL | 按深度衰减的佣金率（depth×0.8×基础率） |
| `is_rewarded` | BOOL | 奖励是否发放 |

### 5. `upgrade_records` 升级记录表
| 字段 | 类型 | 说明 |
|------|------|------|
| `old_level_id` / `new_level_id` | FK | 变更前后等级 |
| `upgrade_type` | TINYINT | 1自动/2手动/3邀请码/4后台调整 |
| `achievement_at_upgrade` | DECIMAL | 升级时的业绩快照 |
| `invite_count_at_upgrade` | INT | 升级时的邀请数快照 |
| `reward_bonus` | DECIMAL | 对应等级的升级奖励 |

---

## 快速启动

### 后端（Laravel 10 + PHP 8.1+）

```bash
cd backend

# 1. 安装依赖
composer install

# 2. 配置环境
cp .env.example .env
# 编辑 .env 配置数据库连接
php artisan key:generate

# 3. 初始化数据库（二选一）
# 方式A：执行初始化 SQL（推荐，包含完整测试数据）
mysql -u root -p < ../database/init.sql

# 方式B：Laravel 迁移 + 手动填充
php artisan migrate

# 4. 启动服务（默认 http://localhost:8000）
php artisan serve
```

### 前端（Vue 3 + Vite + Node 16+）

```bash
cd frontend

# 1. 安装依赖
npm install

# 2. 启动开发服务器（默认 http://localhost:3000，已配置代理 /api → :8000）
npm run dev

# 3. 生产构建
npm run build
```

---

## 核心业务流程

### 邀请码流转
```
生成邀请码（配置等级/奖励/有效期）
      ↓
用户使用邀请码注册
      ↓
┌─ 校验：存在 + 可用（状态/次数/有效期）+ 非自用
├─ 建立 User.inviter_id 关联
├─ InviteChain::createInviteChain()  → 自动创建祖先链路记录
├─ 若邀请码绑定等级 → 自动升级（UpgradeRecord）
└─ 邀请码 used_count++ → 检查是否用完
```

### 等级升级
```
方式一：自动升级
  checkAutoUpgrade() 遍历用户
    → DealerLevel::findNextLevel(业绩, 邀请数)
    → 匹配到更高等级 → UpgradeRecord::recordUpgrade()

方式二：邀请码升级
  使用绑定等级的邀请码 → 立即触发

方式三：手动/后台升级
  管理员操作，不校验任何条件，记录类型区分
```

### 佣金分配
```
User A 业绩新增 $X
  → 遍历所有祖先（invite_path 解析）
    → depth=1 佣金: X × 18%
    → depth=2 佣金: X × 18% × 0.8
    → depth=3 佣金: X × 18% × 0.6
    → ...（最多10层）
```

---

## API 接口一览

### 经销商等级 `/api/dealer-levels`
| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/` | 列表（分页+筛选） |
| GET | `/all` | 全部（下拉选择用） |
| GET | `/stats` | 统计（等级人数分布） |
| GET | `/{id}` | 详情 |
| POST | `/` | 创建 |
| PUT | `/{id}` | 更新 |
| DELETE | `/{id}` | 删除 |
| PATCH | `/{id}/toggle` | 启用/禁用 |

### 邀请码 `/api/invite-codes`
| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/` | 列表 |
| GET | `/code/{code}` | 按码查询详情 |
| GET | `/check` | 实时校验可用性 |
| GET | `/stats` | 使用统计 |
| POST | `/` | 单个生成 |
| POST | `/batch` | 批量生成 |
| PATCH | `/{id}/toggle` | 启停 |

### 邀请链路 `/api/invite-chains`
| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/user/{id}/tree` | 树状结构（指定深度） |
| GET | `/user/{id}/lineage` | 祖先链路 |
| GET | `/user/{id}/stats` | 邀请统计 |
| POST | `/use-code` | 使用邀请码（核心流程） |
| POST | `/create` | 手动建邀请关系 |
| PATCH | `/{id}/reward` | 发奖励 |

### 升级记录 `/api/upgrade-records`
| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/user/{id}/history` | 用户升级历史 |
| GET | `/stats` | 全局/按类型/按时间统计 |
| POST | `/manual-upgrade` | 手动升级 |
| POST | `/check-auto-upgrade` | 自动升级检查（支持DryRun） |
| PATCH | `/reward-all-pending` | 一键发全部待奖励 |

---

所有代码文件均已生成，包含完整的逻辑、注释和前端Mock数据（离线可演示所有页面效果）。
