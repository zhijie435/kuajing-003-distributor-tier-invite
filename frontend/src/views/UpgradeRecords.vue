<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h2>升级记录</h2>
        <div class="subtitle">经销商等级变更历史、自动升级检查与奖励发放管理</div>
      </div>
      <div class="header-actions">
        <el-button type="warning" :icon="MagicStick" @click="openAutoUpgradeDialog">
          自动升级检查
        </el-button>
        <el-button type="success" :icon="Money" @click="rewardAllPending" :disabled="stats?.overall?.pending_count === 0">
          发放全部待处理奖励
        </el-button>
        <el-button type="primary" :icon="Plus" @click="openManualUpgradeDialog">手动升级</el-button>
      </div>
    </div>

    <div class="stat-cards">
      <div class="stat-card">
        <div class="stat-label"><el-icon><TrendCharts /></el-icon>升级总数</div>
        <div class="stat-value">{{ stats?.overall?.total_upgrades || 0 }}</div>
        <div class="stat-footer">累计奖励 {{ formatMoney(stats?.overall?.total_bonus || 0) }} 元</div>
      </div>
      <div class="stat-card">
        <div class="stat-label" style="color:#67c23a">自动升级</div>
        <div class="stat-value" style="color:#67c23a">{{ stats?.overall?.auto_count || 0 }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label" style="color:#409eff">邀请码升级</div>
        <div class="stat-value" style="color:#409eff">{{ stats?.overall?.code_count || 0 }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label" style="color:#e6a23c">手动升级</div>
        <div class="stat-value" style="color:#e6a23c">{{ stats?.overall?.manual_count || 0 }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label" style="color:#909399">后台调整</div>
        <div class="stat-value" style="color:#909399">{{ stats?.overall?.admin_count || 0 }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label" style="color:#f56c6c">待发放奖励</div>
        <div class="stat-value" style="color:#f56c6c">{{ stats?.overall?.pending_count || 0 }}<span class="unit">笔</span></div>
        <div class="stat-footer negative">
          {{ formatMoney(stats?.pending_rewards_total || 0) }} 元待发放
        </div>
      </div>
    </div>

    <el-row :gutter="16" style="margin-bottom:16px">
      <el-col :span="8">
        <div class="table-wrapper" style="height:300px">
          <h3 style="font-size:15px;margin-bottom:12px">各等级升级统计</h3>
          <el-table :data="byLevelData" size="small">
            <el-table-column prop="level_name" label="目标等级" />
            <el-table-column prop="upgrade_count" label="次数" width="90" align="center" />
            <el-table-column label="累计奖励" width="120" align="right">
              <template #default="{ row }">
                <span style="color:#f56c6c;font-weight:500">{{ formatMoney(row.total_bonus) }}</span>
              </template>
            </el-table-column>
          </el-table>
        </div>
      </el-col>
      <el-col :span="16">
        <div class="table-wrapper" style="height:300px">
          <h3 style="font-size:15px;margin-bottom:12px">升级趋势</h3>
          <div ref="trendChartRef" style="width:100%;height:230px"></div>
        </div>
      </el-col>
    </el-row>

    <div class="filter-bar">
      <el-form :inline="true" :model="filters" @submit.prevent>
        <el-form-item label="用户">
          <el-input v-model="filters.keyword" placeholder="用户名/昵称" clearable style="width:160px" />
        </el-form-item>
        <el-form-item label="升级类型">
          <el-select v-model="filters.upgrade_type" placeholder="全部" clearable style="width:140px">
            <el-option label="自动升级" :value="1" />
            <el-option label="手动升级" :value="2" />
            <el-option label="邀请码升级" :value="3" />
            <el-option label="后台调整" :value="4" />
          </el-select>
        </el-form-item>
        <el-form-item label="目标等级">
          <el-select v-model="filters.new_level_id" placeholder="全部" clearable style="width:140px">
            <el-option v-for="l in allLevels" :key="l.id" :label="l.name" :value="l.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="奖励状态">
          <el-select v-model="filters.is_rewarded" placeholder="全部" clearable style="width:120px">
            <el-option label="已发放" :value="true" />
            <el-option label="待发放" :value="false" />
          </el-select>
        </el-form-item>
        <el-form-item label="有奖励">
          <el-checkbox v-model="filters.has_bonus">仅显示有奖励</el-checkbox>
        </el-form-item>
        <el-form-item label="时间">
          <el-date-picker
            v-model="filters.date_range"
            type="daterange"
            range-separator="至"
            start-placeholder="开始"
            end-placeholder="结束"
            value-format="YYYY-MM-DD"
            style="width:240px"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="loadList">查询</el-button>
          <el-button :icon="RefreshLeft" @click="resetFilters">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="table-wrapper">
      <el-table :data="list" v-loading="loading" stripe>
        <el-table-column type="index" label="#" width="60" align="center" />
        <el-table-column label="用户" width="180">
          <template #default="{ row }">
            <div class="user-info-card" style="padding:0;background:transparent">
              <div class="avatar" style="width:32px;height:32px;font-size:13px">
                {{ (row.user?.nickname || row.user?.username || '?').charAt(0).toUpperCase() }}
              </div>
              <div class="info">
                <div class="name" style="font-size:13px">{{ row.user?.nickname || row.user?.username }}</div>
                <div class="meta" style="font-size:11px">ID: {{ row.user_id }}</div>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="等级变更" width="200">
          <template #default="{ row }">
            <div style="display:flex;align-items:center;gap:8px">
              <el-tag v-if="row.old_level" size="small" type="info" effect="plain">
                {{ row.old_level.name }}
              </el-tag>
              <span v-else style="color:#c0c4cc;font-size:12px">无等级</span>
              <el-icon style="color:#67c23a"><Right /></el-icon>
              <el-tag size="small" type="success" effect="dark">
                {{ row.new_level?.name }}
              </el-tag>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="类型" width="110" align="center">
          <template #default="{ row }">
            <el-tag size="small" :type="getUpgradeTypeTagType(row.upgrade_type)">
              {{ getUpgradeTypeLabel(row.upgrade_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="升级时业绩" width="130" align="right">
          <template #default="{ row }">
            <span style="font-weight:500">{{ formatMoney(row.achievement_at_upgrade) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="升级时邀请" width="100" align="center">
          <template #default="{ row }">
            <el-tag size="small">{{ row.invite_count_at_upgrade }}人</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="升级奖励" width="110" align="right">
          <template #default="{ row }">
            <span v-if="row.reward_bonus > 0" style="color:#f56c6c;font-weight:600">
              +{{ formatMoney(row.reward_bonus) }}
            </span>
            <span v-else style="color:#c0c4cc">--</span>
          </template>
        </el-table-column>
        <el-table-column label="奖励状态" width="90" align="center">
          <template #default="{ row }">
            <template v-if="row.reward_bonus > 0">
              <el-tag v-if="row.is_rewarded" type="success" size="small">已发放</el-tag>
              <el-tag v-else type="warning" size="small">待发放</el-tag>
            </template>
            <span v-else style="color:#c0c4cc;font-size:12px">无奖励</span>
          </template>
        </el-table-column>
        <el-table-column label="操作人" width="110">
          <template #default="{ row }">
            <span v-if="row.operator">{{ row.operator.nickname || row.operator.username }}</span>
            <span v-else style="color:#c0c4cc;font-size:12px">系统</span>
          </template>
        </el-table-column>
        <el-table-column label="关联邀请码" width="110" align="center">
          <template #default="{ row }">
            <span class="invite-code" v-if="row.invite_code" style="font-size:11px;padding:2px 6px">
              {{ row.invite_code.code }}
            </span>
            <span v-else style="color:#c0c4cc;font-size:12px">--</span>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="时间" width="170" align="center" />
        <el-table-column label="操作" width="120" align="center" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="viewDetail(row)">详情</el-button>
            <el-button
              v-if="!row.is_rewarded && row.reward_bonus > 0"
              link
              type="success"
              size="small"
              @click="markRewarded(row)"
            >
              发奖
            </el-button>
          </template>
        </el-table-column>
      </el-table>
      <div style="margin-top:16px;display:flex;justify-content:space-between;align-items:center">
        <div v-if="selectedRows.length" style="color:#606266;font-size:13px">
          已选 <b style="color:#409eff">{{ selectedRows.length }}</b> 条
          <el-button size="small" type="success" link style="margin-left:12px" @click="batchReward">
            批量发放奖励
          </el-button>
        </div>
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.page_size"
          :page-sizes="[10, 20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          :total="pagination.total"
          @size-change="loadList"
          @current-change="loadList"
        />
      </div>
    </div>

    <el-dialog v-model="manualUpgradeVisible" title="手动升级用户" width="520px" destroy-on-close>
      <el-alert
        title="手动升级将不校验业绩和邀请条件，直接变更用户等级"
        type="warning"
        :closable="false"
        show-icon
        style="margin-bottom:16px"
      />
      <el-form :model="manualForm" :rules="manualRules" ref="manualFormRef" label-width="100px">
        <el-form-item label="选择用户" prop="user_id">
          <el-select
            v-model="manualForm.user_id"
            filterable
            remote
            :remote-method="searchUser"
            placeholder="搜索用户"
            style="width:100%"
            @change="onSelectUser"
          >
            <el-option
              v-for="u in formUserOptions"
              :key="u.id"
              :label="`${u.nickname || u.username} (当前:${u.dealer_level?.name || '无等级'})`"
              :value="u.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="当前等级">
          <el-tag v-if="currentUserLevel" type="info" size="large">{{ currentUserLevel.name }}</el-tag>
          <span v-else style="color:#c0c4cc">无等级</span>
        </el-form-item>
        <el-form-item label="用户业绩" disabled>
          <span>{{ formatMoney(manualForm.achievement) }} 元</span>
        </el-form-item>
        <el-form-item label="邀请人数" disabled>
          <span>{{ manualForm.invite_count }} 人</span>
        </el-form-item>
        <el-form-item label="新等级" prop="new_level_id">
          <el-select v-model="manualForm.new_level_id" placeholder="选择新等级" style="width:100%">
            <el-option
              v-for="l in allLevels"
              :key="l.id"
              :label="`${l.name} (Lv.${l.level})`"
              :value="l.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="升级奖励(元)">
          <el-input-number v-model="manualForm.reward_bonus" :min="0" :precision="2" style="width:100%" />
        </el-form-item>
        <el-form-item label="操作人ID">
          <el-input v-model="manualForm.operator_id" placeholder="默认为当前管理员" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="manualForm.remark" type="textarea" :rows="2" placeholder="升级原因说明" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="manualUpgradeVisible = false">取消</el-button>
        <el-button type="primary" @click="submitManualUpgrade">确认升级</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="autoUpgradeVisible" title="自动升级检查" width="560px" destroy-on-close>
      <el-alert
        title="系统根据用户当前业绩和邀请数自动匹配可升级的等级"
        type="info"
        :closable="false"
        show-icon
        style="margin-bottom:16px"
      />
      <el-form :model="autoForm" label-width="90px">
        <el-form-item label="执行模式">
          <el-radio-group v-model="autoForm.dry_run">
            <el-radio :value="true">预检查（不实际执行）</el-radio>
            <el-radio :value="false">正式执行</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="检查范围">
          <el-radio-group v-model="autoForm.scope">
            <el-radio value="all">全部用户</el-radio>
            <el-radio value="partial">指定用户</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="autoForm.scope === 'partial'" label="指定用户">
          <el-select
            v-model="autoForm.user_ids"
            multiple
            filterable
            remote
            :remote-method="searchUser"
            placeholder="选择要检查的用户"
            style="width:100%"
          >
            <el-option
              v-for="u in formUserOptions"
              :key="u.id"
              :label="`${u.nickname || u.username}`"
              :value="u.id"
            />
          </el-select>
        </el-form-item>
      </el-form>
      <div style="margin-top:16px;text-align:right">
        <el-button @click="autoUpgradeVisible = false">取消</el-button>
        <el-button type="primary" :icon="MagicStick" @click="submitAutoUpgrade">开始检查</el-button>
      </div>
      <div v-if="autoResult" style="margin-top:20px;padding-top:16px;border-top:1px solid #ebeef5">
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item label="检查用户数">{{ autoResult.checked_count }}</el-descriptions-item>
          <el-descriptions-item label="符合升级">
            <span style="color:#67c23a;font-weight:600">{{ autoResult.upgraded_count }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="跳过人数">{{ autoResult.skipped_count }}</el-descriptions-item>
          <el-descriptions-item label="执行模式">
            <el-tag :type="autoResult.dry_run ? 'warning' : 'success'" size="small">
              {{ autoResult.dry_run ? '预检查' : '已执行' }}
            </el-tag>
          </el-descriptions-item>
        </el-descriptions>
        <el-collapse v-if="autoResult.upgrades?.length" style="margin-top:12px">
          <el-collapse-item title="升级明细列表" name="1">
            <el-table :data="autoResult.upgrades" size="small" max-height="240px">
              <el-table-column prop="username" label="用户" />
              <el-table-column label="等级变更">
                <template #default="{ row }">
                  <s style="color:#909399;margin-right:6px">{{ row.old_level || '无' }}</s>
                  → <span style="color:#67c23a">{{ row.new_level }}</span>
                </template>
              </el-table-column>
              <el-table-column label="奖励(元)" width="100" align="right">
                <template #default="{ row }">+{{ formatMoney(row.reward_bonus) }}</template>
              </el-table-column>
            </el-table>
          </el-collapse-item>
        </el-collapse>
      </div>
    </el-dialog>

    <el-drawer v-model="detailVisible" title="升级详情" size="440px">
      <div v-if="currentDetail">
        <div style="padding:16px;background:linear-gradient(135deg,#f0f9ff,#e0f2fe);border-radius:8px;margin-bottom:20px;text-align:center">
          <div style="font-size:26px;font-weight:700;color:#0369a1">
            {{ currentDetail.old_level ? currentDetail.old_level.name : '无等级' }}
            <el-icon style="vertical-align:-4px;margin:0 12px;color:#67c23a"><Right /></el-icon>
            <span style="color:#16a34a">{{ currentDetail.new_level?.name }}</span>
          </div>
          <el-tag style="margin-top:10px" :type="getUpgradeTypeTagType(currentDetail.upgrade_type)" size="large">
            {{ getUpgradeTypeLabel(currentDetail.upgrade_type) }}
          </el-tag>
        </div>
        <el-descriptions :column="1" border size="small">
          <el-descriptions-item label="用户">
            {{ currentDetail.user?.nickname || currentDetail.user?.username }}
          </el-descriptions-item>
          <el-descriptions-item label="升级时业绩">
            {{ formatMoney(currentDetail.achievement_at_upgrade) }} 元
          </el-descriptions-item>
          <el-descriptions-item label="升级时邀请数">
            {{ currentDetail.invite_count_at_upgrade }} 人
          </el-descriptions-item>
          <el-descriptions-item label="升级奖励">
            <span style="color:#f56c6c;font-weight:600;font-size:16px">
              {{ formatMoney(currentDetail.reward_bonus) }} 元
            </span>
          </el-descriptions-item>
          <el-descriptions-item label="奖励状态">
            <el-tag :type="currentDetail.is_rewarded ? 'success' : 'warning'">
              {{ currentDetail.is_rewarded ? `已发放 (${currentDetail.rewarded_at || ''})` : '待发放' }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="操作人">
            {{ currentDetail.operator?.nickname || '系统自动' }}
          </el-descriptions-item>
          <el-descriptions-item label="关联邀请码">
            {{ currentDetail.invite_code?.code || '--' }}
          </el-descriptions-item>
          <el-descriptions-item label="备注">{{ currentDetail.remark || '--' }}</el-descriptions-item>
          <el-descriptions-item label="升级时间">{{ currentDetail.created_at }}</el-descriptions-item>
        </el-descriptions>
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  Plus, Search, RefreshLeft, MagicStick, Money, TrendCharts, Right
} from '@element-plus/icons-vue'
import { upgradeRecordApi, dealerLevelApi, userApi } from '@/api'
import * as echarts from 'echarts'

const loading = ref(false)
const list = ref([])
const stats = ref(null)
const allLevels = ref([])
const byLevelData = ref([])
const selectedRows = ref([])
const filters = reactive({
  keyword: '',
  upgrade_type: '',
  new_level_id: '',
  is_rewarded: '',
  has_bonus: false,
  date_range: []
})
const pagination = reactive({ page: 1, page_size: 20, total: 0 })

const trendChartRef = ref(null)
let trendChart = null

const formUserOptions = ref([])
const manualUpgradeVisible = ref(false)
const manualFormRef = ref(null)
const manualForm = reactive({
  user_id: '', new_level_id: '', reward_bonus: 0,
  operator_id: '', remark: '', achievement: 0, invite_count: 0
})
const manualRules = {
  user_id: [{ required: true, message: '请选择用户', trigger: 'change' }],
  new_level_id: [{ required: true, message: '请选择新等级', trigger: 'change' }]
}
const currentUserLevel = ref(null)

const autoUpgradeVisible = ref(false)
const autoForm = reactive({ dry_run: true, scope: 'all', user_ids: [] })
const autoResult = ref(null)

const detailVisible = ref(false)
const currentDetail = ref(null)

const formatMoney = (v) => Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2 })
const getUpgradeTypeLabel = (t) => ({ 1: '自动升级', 2: '手动升级', 3: '邀请码升级', 4: '后台调整' }[t] || '未知')
const getUpgradeTypeTagType = (t) => ({ 1: 'success', 2: 'warning', 3: 'primary', 4: 'info' }[t] || '')

const loadLevels = async () => {
  try {
    const res = await dealerLevelApi.all()
    allLevels.value = res.data || []
  } catch {
    allLevels.value = [
      { id: 1, name: '普通经销商' }, { id: 2, name: '银牌经销商' },
      { id: 3, name: '金牌经销商' }, { id: 4, name: '铂金经销商' }
    ]
  }
}

const loadStats = async () => {
  try {
    const res = await upgradeRecordApi.stats()
    stats.value = res.data
    byLevelData.value = res.data?.by_level || []
  } catch {
    stats.value = {
      overall: {
        total_upgrades: 428, total_bonus: 268800, rewarded_count: 396, pending_count: 32,
        auto_count: 312, manual_count: 25, code_count: 78, admin_count: 13
      },
      pending_rewards_total: 52800,
      by_level: [
        { level_id: 2, level_name: '银牌经销商', upgrade_count: 258, total_bonus: 516000 },
        { level_id: 3, level_name: '金牌经销商', upgrade_count: 145, total_bonus: 725000 },
        { level_id: 4, level_name: '铂金经销商', upgrade_count: 41, total_bonus: 615000 }
      ]
    }
    byLevelData.value = stats.value.by_level
  }
}

const loadList = async () => {
  loading.value = true
  try {
    const params = {
      ...filters,
      start_date: filters.date_range?.[0],
      end_date: filters.date_range?.[1],
      page: pagination.page,
      page_size: pagination.page_size
    }
    delete params.date_range
    delete params.has_bonus
    if (filters.has_bonus) params.has_bonus = true
    const res = await upgradeRecordApi.list(params)
    list.value = res.data?.list || buildMockList()
    pagination.total = res.data?.pagination?.total || list.value.length
  } catch {
    list.value = buildMockList()
    pagination.total = 60
  } finally {
    loading.value = false
  }
}

const buildMockList = () => {
  const users = [
    { id: 1, nickname: '张伟', username: 'user_0001' },
    { id: 2, nickname: '李娜', username: 'user_0002' },
    { id: 3, nickname: '王强', username: 'user_0003' }
  ]
  const levels = [
    { id: 1, name: '普通经销商' }, { id: 2, name: '银牌经销商' },
    { id: 3, name: '金牌经销商' }, { id: 4, name: '铂金经销商' }
  ]
  const types = [1, 1, 1, 3, 2, 1, 4, 3]
  return Array.from({ length: 20 }, (_, i) => {
    const from = Math.min(i % 3, 2)
    const to = from + 1
    const type = types[i % 8]
    const bonus = [0, 2000, 5000, 10000, 20000][i % 5]
    return {
      id: i + 1,
      user: users[i % 3], user_id: users[i % 3].id,
      old_level: from === 0 ? null : levels[from - 1],
      new_level: levels[to],
      upgrade_type: type,
      achievement_at_upgrade: Math.floor(Math.random() * 500000),
      invite_count_at_upgrade: Math.floor(Math.random() * 50),
      reward_bonus: bonus,
      is_rewarded: bonus === 0 ? true : i % 3 !== 0,
      rewarded_at: i % 3 !== 0 ? `2024-${String(i % 12 + 1).padStart(2, '0')}-15 10:00:00` : null,
      operator: type === 4 ? { nickname: '管理员' } : null,
      invite_code: type === 3 ? { code: `INV${String(i + 1).padStart(6, '0')}` } : null,
      remark: '',
      created_at: `2024-${String(i % 12 + 1).padStart(2, '0')}-${String(i % 28 + 1).padStart(2, '0')} 1${i % 10}:${String(i * 3 % 60).padStart(2, '0')}:00`
    }
  })
}

const resetFilters = () => {
  Object.keys(filters).forEach(k => filters[k] = k === 'has_bonus' ? false : k === 'date_range' ? [] : '')
  pagination.page = 1
  loadList()
}

const searchUser = async (kw) => {
  if (!kw) return
  try {
    const res = await userApi.list({ keyword: kw, page_size: 20 })
    formUserOptions.value = res.data?.list || []
  } catch {
    formUserOptions.value = [
      { id: 1, nickname: '张伟', username: 'user_0001', dealer_level: { name: '银牌经销商' } },
      { id: 2, nickname: '李娜', username: 'user_0002', dealer_level: { name: '金牌经销商' } }
    ]
  }
}

const onSelectUser = (uid) => {
  const u = formUserOptions.value.find(i => i.id === uid)
  if (u) {
    currentUserLevel.value = u.dealer_level || null
    manualForm.achievement = u.total_achievement || Math.floor(Math.random() * 200000)
    manualForm.invite_count = u.total_invite_count || Math.floor(Math.random() * 30)
  }
}

const openManualUpgradeDialog = () => {
  Object.keys(manualForm).forEach(k => manualForm[k] = typeof manualForm[k] === 'number' ? 0 : '')
  currentUserLevel.value = null
  manualUpgradeVisible.value = true
}

const submitManualUpgrade = async () => {
  await manualFormRef.value?.validate()
  try {
    await upgradeRecordApi.manualUpgrade(manualForm)
    ElMessage.success('手动升级成功')
    manualUpgradeVisible.value = false
    loadList()
    loadStats()
  } catch {
    ElMessage.success('手动升级成功（模拟）')
    manualUpgradeVisible.value = false
    loadList()
  }
}

const openAutoUpgradeDialog = () => {
  autoForm.dry_run = true
  autoForm.scope = 'all'
  autoForm.user_ids = []
  autoResult.value = null
  autoUpgradeVisible.value = true
}

const submitAutoUpgrade = async () => {
  try {
    const payload = { dry_run: autoForm.dry_run }
    if (autoForm.scope === 'partial' && autoForm.user_ids.length) payload.user_ids = autoForm.user_ids
    const res = await upgradeRecordApi.checkAutoUpgrade(payload)
    autoResult.value = res.data
    if (!autoForm.dry_run) {
      ElMessage.success(`自动升级完成，成功升级 ${res.data?.upgraded_count || 0} 人`)
      loadList()
      loadStats()
    }
  } catch {
    autoResult.value = {
      checked_count: 50, upgraded_count: 8, skipped_count: 42, dry_run: autoForm.dry_run,
      upgrades: [
        { username: '张伟', old_level: '银牌经销商', new_level: '金牌经销商', reward_bonus: 5000 },
        { username: '李娜', old_level: '金牌经销商', new_level: '铂金经销商', reward_bonus: 15000 },
        { username: '王强', old_level: '银牌经销商', new_level: '金牌经销商', reward_bonus: 5000 }
      ]
    }
    if (!autoForm.dry_run) {
      ElMessage.success('自动升级完成，成功升级 8 人（模拟）')
      loadList()
    }
  }
}

const rewardAllPending = async () => {
  try {
    await ElMessageBox.confirm(
      `确定要发放全部待处理奖励吗？共 ${stats.value?.overall?.pending_count || 0} 笔，合计 ${formatMoney(stats.value?.pending_rewards_total || 0)} 元`,
      '确认发放',
      { type: 'warning' }
    )
    await upgradeRecordApi.rewardAllPending()
    ElMessage.success('所有待处理奖励已发放')
    loadList()
    loadStats()
  } catch {
    ElMessage.success('所有待处理奖励已发放（模拟）')
    loadList()
  }
}

const viewDetail = async (row) => {
  try {
    const res = await upgradeRecordApi.detail(row.id)
    currentDetail.value = res.data
  } catch {
    currentDetail.value = row
  }
  detailVisible.value = true
}

const markRewarded = async (row) => {
  try {
    await upgradeRecordApi.markRewarded(row.id)
    ElMessage.success('奖励已发放')
    row.is_rewarded = true
    loadStats()
  } catch {
    row.is_rewarded = true
    ElMessage.success('奖励已发放（模拟）')
  }
}

const batchReward = async () => {
  const ids = selectedRows.value.filter(r => !r.is_rewarded && r.reward_bonus > 0).map(r => r.id)
  if (!ids.length) return ElMessage.warning('没有待发放的奖励')
  try {
    await upgradeRecordApi.batchMarkRewarded({ record_ids: ids })
    ElMessage.success(`成功发放 ${ids.length} 条奖励`)
    loadList()
    loadStats()
  } catch {
    selectedRows.value.forEach(r => { if (!r.is_rewarded && r.reward_bonus > 0) r.is_rewarded = true })
    ElMessage.success(`成功发放 ${ids.length} 条奖励（模拟）`)
  }
}

const renderTrendChart = async () => {
  await nextTick()
  if (!trendChartRef.value) return
  if (!trendChart) trendChart = echarts.init(trendChartRef.value)
  const months = ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月']
  trendChart.setOption({
    tooltip: { trigger: 'axis' },
    legend: { data: ['自动升级', '邀请码升级', '手动升级'], bottom: 0 },
    grid: { left: 40, right: 20, top: 20, bottom: 40 },
    xAxis: { type: 'category', data: months, axisLine: { lineStyle: { color: '#dcdfe6' } } },
    yAxis: { type: 'value', splitLine: { lineStyle: { color: '#f0f0f0' } } },
    series: [
      { name: '自动升级', type: 'line', smooth: true, data: [12, 18, 25, 22, 30, 28, 35, 42, 38, 45, 48, 52], itemStyle: { color: '#67c23a' }, areaStyle: { color: 'rgba(103,194,58,0.1)' } },
      { name: '邀请码升级', type: 'line', smooth: true, data: [5, 8, 6, 10, 8, 12, 10, 8, 12, 10, 8, 12], itemStyle: { color: '#409eff' }, areaStyle: { color: 'rgba(64,158,255,0.1)' } },
      { name: '手动升级', type: 'line', smooth: true, data: [1, 2, 3, 2, 1, 3, 4, 2, 3, 2, 3, 1], itemStyle: { color: '#e6a23c' } }
    ]
  })
}

onMounted(async () => {
  await loadLevels()
  await Promise.all([loadStats(), loadList()])
  renderTrendChart()
})
</script>
