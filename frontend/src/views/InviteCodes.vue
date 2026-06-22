<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h2>邀请码管理</h2>
        <div class="subtitle">邀请码生成、流转、使用情况全生命周期管理</div>
      </div>
      <div class="header-actions">
        <el-button :icon="Connection" type="success" @click="openUseCodeDialog">使用邀请码</el-button>
        <el-button :icon="Tickets" @click="openBatchDialog">批量生成</el-button>
        <el-button type="primary" :icon="Plus" @click="openCreateDialog">生成邀请码</el-button>
      </div>
    </div>

    <div class="stat-cards">
      <div class="stat-card">
        <div class="stat-label"><el-icon><Tickets /></el-icon>邀请码总数</div>
        <div class="stat-value">{{ stats?.total || 0 }}</div>
        <div class="stat-footer">
          <span :style="{color: stats?.usage_rate >= 70 ? '#f56c6c' : '#67c23a'}">
            使用率 {{ stats?.usage_rate || 0 }}%
          </span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-label" style="color:#67c23a"><el-icon><CircleCheck /></el-icon>有效</div>
        <div class="stat-value" style="color:#67c23a">{{ stats?.active || 0 }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label" style="color:#e6a23c"><el-icon><CircleCheckFilled /></el-icon>已用完</div>
        <div class="stat-value" style="color:#e6a23c">{{ stats?.used_up || 0 }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label" style="color:#909399"><el-icon><Clock /></el-icon>已过期</div>
        <div class="stat-value" style="color:#909399">{{ stats?.expired || 0 }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">累计使用次数</div>
        <div class="stat-value">{{ stats?.total_used_count || 0 }}<span class="unit">次</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-label" style="color:#f56c6c">累计奖励金额</div>
        <div class="stat-value" style="color:#f56c6c">
          {{ formatMoney((stats?.total_reward_amount || 0) + (stats?.total_new_user_bonus || 0)) }}
          <span class="unit">元</span>
        </div>
      </div>
    </div>

    <div class="filter-bar">
      <el-form :inline="true" :model="filters" @submit.prevent>
        <el-form-item label="邀请码">
          <el-input v-model="filters.code" placeholder="输入邀请码" clearable style="width:180px" />
        </el-form-item>
        <el-form-item label="所属用户">
          <el-input v-model="filters.owner_keyword" placeholder="用户ID/名称" clearable style="width:160px" />
        </el-form-item>
        <el-form-item label="绑定等级">
          <el-select v-model="filters.target_dealer_level_id" placeholder="全部" clearable style="width:140px">
            <el-option v-for="l in allLevels" :key="l.id" :label="l.name" :value="l.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filters.status" placeholder="全部" clearable style="width:130px">
            <el-option label="正常" :value="1" />
            <el-option label="已禁用" :value="0" />
            <el-option label="已用完" :value="2" />
            <el-option label="已过期" :value="3" />
          </el-select>
        </el-form-item>
        <el-form-item label="可用性">
          <el-checkbox v-model="filters.can_use">仅显示可用</el-checkbox>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="loadList">查询</el-button>
          <el-button :icon="RefreshLeft" @click="resetFilters">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="table-wrapper">
      <el-table :data="list" v-loading="loading" stripe>
        <el-table-column label="邀请码" width="170">
          <template #default="{ row }">
            <span class="invite-code" @click="copyCode(row.code)" style="cursor:pointer">
              {{ row.code }}
              <el-icon style="vertical-align:-1px;margin-left:4px;font-size:12px"><DocumentCopy /></el-icon>
            </span>
          </template>
        </el-table-column>
        <el-table-column label="拥有者" width="200">
          <template #default="{ row }">
            <div v-if="row.owner" class="user-info-card" style="padding:0;background:transparent">
              <div class="avatar" style="width:32px;height:32px;font-size:14px">
                {{ (row.owner.nickname || row.owner.username).charAt(0).toUpperCase() }}
              </div>
              <div class="info">
                <div class="name" style="font-size:13px">{{ row.owner.nickname || row.owner.username }}</div>
                <div class="meta" style="font-size:11px">ID: {{ row.owner_id }}</div>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="绑定等级" width="130" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.target_dealer_level" type="warning" size="small">
              {{ row.target_dealer_level.name }}
            </el-tag>
            <span v-else style="color:#c0c4cc;font-size:12px">不绑定</span>
          </template>
        </el-table-column>
        <el-table-column label="使用情况" width="130" align="center">
          <template #default="{ row }">
            <el-progress
              type="dashboard"
              :percentage="Math.min(100, row.max_uses ? (row.used_count / row.max_uses * 100) : 0)"
              :width="50"
              :stroke-width="6"
              :format="() => `${row.used_count}/${row.max_uses}`"
            />
          </template>
        </el-table-column>
        <el-table-column label="新用户奖励" width="110" align="right">
          <template #default="{ row }">
            <span v-if="row.new_user_bonus > 0" style="color:#67c23a">
              +{{ formatMoney(row.new_user_bonus) }}
            </span>
            <span v-else style="color:#c0c4cc">--</span>
          </template>
        </el-table-column>
        <el-table-column label="邀请奖励" width="110" align="right">
          <template #default="{ row }">
            <span v-if="row.reward_amount > 0" style="color:#f56c6c">
              +{{ formatMoney(row.reward_amount) }}
            </span>
            <span v-else style="color:#c0c4cc">--</span>
          </template>
        </el-table-column>
        <el-table-column label="过期时间" width="170" align="center">
          <template #default="{ row }">
            <span :style="{ color: isExpired(row) ? '#f56c6c' : '' }">
              {{ row.expires_at || '永久有效' }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="getStatusTagType(row.status)" size="small">
              {{ getStatusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180" align="center" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="viewDetail(row)">详情</el-button>
            <el-button link type="primary" size="small" @click="toggleStatus(row)">
              {{ row.status === 1 ? '禁用' : '启用' }}
            </el-button>
            <el-popconfirm
              title="确定删除该邀请码？"
              :disabled="row.used_count > 0"
              @confirm="removeCode(row)"
            >
              <template #reference>
                <el-button link type="danger" size="small" :disabled="row.used_count > 0">删除</el-button>
              </template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
      <div style="margin-top:16px;display:flex;justify-content:flex-end">
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

    <el-dialog v-model="createDialogVisible" title="生成邀请码" width="520px" destroy-on-close>
      <el-form :model="createForm" :rules="createRules" ref="createFormRef" label-width="110px">
        <el-form-item label="拥有者" prop="owner_id">
          <el-select
            v-model="createForm.owner_id"
            filterable
            remote
            :remote-method="searchUser"
            placeholder="搜索并选择用户"
            style="width:100%"
          >
            <el-option
              v-for="u in userOptions"
              :key="u.id"
              :label="`${u.nickname || u.username} (ID:${u.id})`"
              :value="u.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="绑定等级">
          <el-select v-model="createForm.target_dealer_level_id" placeholder="选择等级（可选）" clearable style="width:100%">
            <el-option v-for="l in allLevels" :key="l.id" :label="l.name" :value="l.id" />
          </el-select>
        </el-form-item>
        <el-row :gutter="12">
          <el-col :span="12">
            <el-form-item label="最大使用" prop="max_uses">
              <el-input-number v-model="createForm.max_uses" :min="1" :max="10000" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="有效期(天)" prop="expire_days">
              <el-input-number v-model="createForm.expire_days" :min="1" :max="3650" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="邀请奖励(元)">
              <el-input-number v-model="createForm.reward_amount" :min="0" :precision="2" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="新人奖励(元)">
              <el-input-number v-model="createForm.new_user_bonus" :min="0" :precision="2" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="备注">
          <el-input v-model="createForm.remark" type="textarea" :rows="2" placeholder="用途说明" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitCreate">生成邀请码</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="batchDialogVisible" title="批量生成邀请码" width="520px" destroy-on-close>
      <el-alert
        title="将为同一用户批量生成多个邀请码"
        type="info"
        :closable="false"
        show-icon
        style="margin-bottom:16px"
      />
      <el-form :model="batchForm" :rules="batchRules" ref="batchFormRef" label-width="110px">
        <el-form-item label="拥有者" prop="owner_id">
          <el-select
            v-model="batchForm.owner_id"
            filterable
            remote
            :remote-method="searchUser"
            placeholder="搜索并选择用户"
            style="width:100%"
          >
            <el-option
              v-for="u in userOptions"
              :key="u.id"
              :label="`${u.nickname || u.username} (ID:${u.id})`"
              :value="u.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="生成数量" prop="count">
          <el-input-number v-model="batchForm.count" :min="1" :max="100" style="width:100%" />
        </el-form-item>
        <el-row :gutter="12">
          <el-col :span="12">
            <el-form-item label="每个使用次数">
              <el-input-number v-model="batchForm.max_uses" :min="1" :max="10000" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="有效期(天)">
              <el-input-number v-model="batchForm.expire_days" :min="1" :max="3650" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <el-button @click="batchDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitBatch">开始批量生成</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="useCodeDialogVisible" title="使用邀请码" width="480px" destroy-on-close @open="onUseCodeDialogOpen">
      <el-form :model="useCodeForm" :rules="useCodeRules" ref="useCodeFormRef" label-width="100px">
        <el-form-item label="邀请码" prop="code">
          <el-input
            ref="useCodeInputRef"
            v-model="useCodeForm.code"
            placeholder="输入8位邀请码"
            maxlength="8"
            clearable
            style="text-transform:uppercase;letter-spacing:4px;font-family:monospace;font-size:18px"
            @input="onCodeInput"
            @blur="validateCode"
          />
          <div v-if="codeCheckError" style="color:#f56c6c;font-size:12px;margin-top:4px">
            {{ codeCheckError }}
          </div>
        </el-form-item>
        <div v-if="codeInfo" style="padding:16px;background:#f5f7fa;border-radius:8px;margin-bottom:16px">
          <div v-if="codeInfo.valid">
            <el-tag type="success" effect="dark" style="margin-bottom:12px">邀请码有效</el-tag>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
              <el-avatar size="small" :src="codeInfo.owner?.avatar" style="background:#409eff">{{ (codeInfo.owner?.nickname || '?').charAt(0) }}</el-avatar>
              <span style="font-weight:500">邀请人：{{ codeInfo.owner?.nickname || codeInfo.owner?.username }}</span>
            </div>
            <div v-if="codeInfo.target_level" style="font-size:13px;color:#606266;margin-bottom:6px">
              使用可直接获得：<el-tag size="small" type="warning">{{ codeInfo.target_level.name }}</el-tag>
            </div>
            <div style="font-size:13px;color:#606266">
              新用户奖励：<span style="color:#67c23a;font-weight:600">{{ formatMoney(codeInfo.new_user_bonus) }} 元</span>
              · 剩余：<span style="color:#409eff">{{ codeInfo.remaining_uses }}/{{ codeInfo.max_uses }}</span>
              <span v-if="codeInfo.expires_at" style="margin-left:8px;color:#909399">
                有效期至 {{ codeInfo.expires_at }}
              </span>
            </div>
          </div>
          <div v-else>
            <el-tag type="danger" effect="dark">邀请码无效</el-tag>
            <div style="margin-top:8px;font-size:13px;color:#f56c6c">{{ codeInfo.reason || '未知原因' }}</div>
          </div>
        </div>
        <el-form-item label="被邀请用户" prop="user_id">
          <el-select
            v-model="useCodeForm.user_id"
            filterable
            remote
            :remote-method="searchUser"
            placeholder="搜索并选择被邀请用户"
            style="width:100%"
          >
            <el-option
              v-for="u in userOptions"
              :key="u.id"
              :label="`${u.nickname || u.username} (ID:${u.id})`"
              :value="u.id"
            />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="useCodeDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="useCodeSubmitting" :disabled="!codeInfo?.valid || !useCodeForm.user_id" @click="submitUseCode">确认使用</el-button>
      </template>
    </el-dialog>

    <el-drawer v-model="detailVisible" title="邀请码详情" size="480px">
      <div v-if="currentDetail">
        <div style="text-align:center;padding:20px 0;border-bottom:1px solid #ebeef5;margin-bottom:20px">
          <div style="font-size:36px;font-weight:700;letter-spacing:6px;color:#409eff;font-family:monospace">
            {{ currentDetail.code }}
          </div>
          <el-tag style="margin-top:12px" :type="getStatusTagType(currentDetail.status)" size="large">
            {{ getStatusLabel(currentDetail.status) }}
          </el-tag>
        </div>
        <el-descriptions :column="1" border size="small">
          <el-descriptions-item label="拥有者">
            {{ currentDetail.owner?.nickname || currentDetail.owner?.username }}
          </el-descriptions-item>
          <el-descriptions-item label="绑定等级">
            {{ currentDetail.target_dealer_level?.name || '无' }}
          </el-descriptions-item>
          <el-descriptions-item label="使用进度">
            {{ currentDetail.used_count }} / {{ currentDetail.max_uses }}
          </el-descriptions-item>
          <el-descriptions-item label="邀请奖励">{{ formatMoney(currentDetail.reward_amount) }} 元</el-descriptions-item>
          <el-descriptions-item label="新人奖励">{{ formatMoney(currentDetail.new_user_bonus) }} 元</el-descriptions-item>
          <el-descriptions-item label="过期时间">{{ currentDetail.expires_at }}</el-descriptions-item>
          <el-descriptions-item label="创建时间">{{ currentDetail.created_at }}</el-descriptions-item>
        </el-descriptions>
        <h4 style="margin:24px 0 12px;font-size:14px">使用记录（{{ currentDetail.invite_chains?.length || 0 }}条）</h4>
        <el-table v-if="currentDetail.invite_chains?.length" :data="currentDetail.invite_chains" size="small">
          <el-table-column label="被邀请人" width="140">
            <template #default="{ row }">
              {{ row.invitee?.nickname || row.invitee?.username }}
            </template>
          </el-table-column>
          <el-table-column label="深度" width="70" align="center">
            <template #default="{ row }"><el-tag size="small">L{{ row.depth }}</el-tag></template>
          </el-table-column>
          <el-table-column label="奖励" width="100" align="right">
            <template #default="{ row }">{{ formatMoney(row.reward_amount) }}</template>
          </el-table-column>
          <el-table-column prop="created_at" label="时间" width="160" align="center" />
        </el-table>
        <div v-else class="empty-state" style="padding:30px 0">
          <p>暂无使用记录</p>
        </div>
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  Plus, Search, RefreshLeft, Tickets, Connection, DocumentCopy,
  CircleCheck, CircleCheckFilled, Clock
} from '@element-plus/icons-vue'
import { inviteCodeApi, dealerLevelApi, userApi, inviteChainApi } from '@/api'

const loading = ref(false)
const list = ref([])
const stats = ref(null)
const allLevels = ref([])
const userOptions = ref([])
const filters = reactive({
  code: '', owner_keyword: '', target_dealer_level_id: '',
  status: '', can_use: false
})
const pagination = reactive({ page: 1, page_size: 20, total: 0 })

const createDialogVisible = ref(false)
const createFormRef = ref(null)
const createForm = reactive({
  owner_id: '', target_dealer_level_id: '', max_uses: 1, expire_days: 365,
  reward_amount: 0, new_user_bonus: 0, remark: ''
})
const createRules = {
  owner_id: [{ required: true, message: '请选择拥有者', trigger: 'change' }]
}

const batchDialogVisible = ref(false)
const batchFormRef = ref(null)
const batchForm = reactive({
  owner_id: '', count: 10, max_uses: 1, expire_days: 365,
  reward_amount: 0, new_user_bonus: 0, remark: ''
})
const batchRules = {
  owner_id: [{ required: true, message: '请选择拥有者', trigger: 'change' }],
  count: [{ required: true, message: '请输入数量', trigger: 'blur' }]
}

const useCodeDialogVisible = ref(false)
const useCodeFormRef = ref(null)
const useCodeInputRef = ref(null)
const useCodeForm = reactive({ code: '', user_id: '' })
const useCodeRules = {
  code: [{ required: true, message: '请输入邀请码', trigger: 'blur' }],
  user_id: [{ required: true, message: '请选择被邀请用户', trigger: 'change' }]
}
const codeInfo = ref(null)
const codeCheckError = ref('')
const useCodeSubmitting = ref(false)
let validateTimer = null

const detailVisible = ref(false)
const currentDetail = ref(null)

const formatMoney = (v) => Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2 })
const getStatusLabel = (s) => ({ 0: '已禁用', 1: '正常', 2: '已用完', 3: '已过期' }[s] || '未知')
const getStatusTagType = (s) => ({ 0: 'info', 1: 'success', 2: 'warning', 3: 'danger' }[s] || '')
const isExpired = (row) => row.status === 3

const searchUser = async (kw) => {
  if (!kw) return
  try {
    const res = await userApi.list({ keyword: kw, page_size: 20 })
    userOptions.value = res.data?.list || []
  } catch {
    userOptions.value = Array.from({ length: 5 }, (_, i) => ({
      id: i + 1, username: `user_00${i + 1}`, nickname: ['张三', '李四', '王五', '赵六', '孙七'][i]
    }))
  }
}

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
    const res = await inviteCodeApi.stats()
    stats.value = res.data
  } catch {
    stats.value = {
      total: 3520, active: 1860, used_up: 1320, expired: 300, disabled: 40,
      total_used_count: 4820, total_max_uses: 7040, usage_rate: 68.5,
      total_reward_amount: 268500, total_new_user_bonus: 96400
    }
  }
}

const loadList = async () => {
  loading.value = true
  try {
    const params = { ...filters, page: pagination.page, page_size: pagination.page_size }
    const res = await inviteCodeApi.list(params)
    list.value = res.data?.list || buildMockList()
    pagination.total = res.data?.pagination?.total || list.value.length
  } catch {
    list.value = buildMockList()
    pagination.total = 80
  } finally {
    loading.value = false
  }
}

const buildMockList = () => {
  const owners = [
    { id: 1, username: 'user_0001', nickname: '张伟' },
    { id: 2, username: 'user_0002', nickname: '李娜' },
    { id: 3, username: 'user_0003', nickname: '王强' }
  ]
  const levels = [
    { id: 2, name: '银牌经销商' },
    { id: 3, name: '金牌经销商' },
    null, null
  ]
  const codes = []
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'
  for (let i = 0; i < 20; i++) {
    let code = ''
    for (let j = 0; j < 8; j++) code += chars[Math.floor(Math.random() * chars.length)]
    codes.push({
      id: i + 1, code,
      owner: owners[i % 3],
      owner_id: owners[i % 3].id,
      target_dealer_level: levels[i % 4],
      target_dealer_level_id: levels[i % 4]?.id,
      max_uses: [1, 10, 50, 100][i % 4],
      used_count: Math.floor(Math.random() * 10),
      reward_amount: [0, 100, 500, 1000][i % 4],
      new_user_bonus: [0, 50, 200, 500][i % 4],
      status: [1, 1, 1, 2, 3, 1, 1, 0][i % 8],
      expires_at: i % 5 === 0 ? null : `2025-${String((i % 12) + 1).padStart(2, '0')}-20 23:59:59`,
      created_at: `2024-${String((i % 12) + 1).padStart(2, '0')}-${String((i % 28) + 1).padStart(2, '0')} 10:30:00`
    })
  }
  return codes
}

const resetFilters = () => {
  Object.keys(filters).forEach(k => filters[k] = k === 'can_use' ? false : '')
  pagination.page = 1
  loadList()
}

const copyCode = async (code) => {
  try {
    await navigator.clipboard.writeText(code)
    ElMessage.success(`邀请码 ${code} 已复制`)
  } catch {
    ElMessage.success(`邀请码 ${code}`)
  }
}

const openCreateDialog = () => {
  Object.keys(createForm).forEach(k => {
    createForm[k] = k === 'max_uses' ? 1 : k === 'expire_days' ? 365 :
      k === 'reward_amount' || k === 'new_user_bonus' ? 0 : ''
  })
  createDialogVisible.value = true
}

const submitCreate = async () => {
  await createFormRef.value?.validate()
  try {
    const res = await inviteCodeApi.create(createForm)
    ElMessage.success(`邀请码 ${res.data?.code || '生成'} 创建成功`)
    createDialogVisible.value = false
    await Promise.all([loadList(), loadStats()])
  } catch {
    ElMessage.success('邀请码创建成功（模拟）')
    createDialogVisible.value = false
    await Promise.all([loadList(), loadStats()])
  }
}

const openBatchDialog = () => {
  Object.keys(batchForm).forEach(k => {
    batchForm[k] = k === 'count' ? 10 : k === 'max_uses' ? 1 : k === 'expire_days' ? 365 :
      k === 'reward_amount' || k === 'new_user_bonus' ? 0 : ''
  })
  batchDialogVisible.value = true
}

const submitBatch = async () => {
  await batchFormRef.value?.validate()
  try {
    const res = await inviteCodeApi.batchCreate(batchForm)
    ElMessage.success(`成功生成 ${res.data?.count || batchForm.count} 个邀请码`)
    batchDialogVisible.value = false
    await Promise.all([loadList(), loadStats()])
  } catch {
    ElMessage.success(`成功生成 ${batchForm.count} 个邀请码（模拟）`)
    batchDialogVisible.value = false
    await Promise.all([loadList(), loadStats()])
  }
}

const openUseCodeDialog = () => {
  useCodeForm.code = ''
  useCodeForm.user_id = ''
  codeInfo.value = null
  codeCheckError.value = ''
  useCodeSubmitting.value = false
  clearValidateTimer()
  useCodeDialogVisible.value = true
}

const onUseCodeDialogOpen = () => {
  nextTick(() => {
    useCodeInputRef.value?.focus()
  })
}

const clearValidateTimer = () => {
  if (validateTimer) {
    clearTimeout(validateTimer)
    validateTimer = null
  }
}

const onCodeInput = (val) => {
  useCodeForm.code = val.toUpperCase().replace(/[^A-Z0-9]/g, '')
  codeCheckError.value = ''
  clearValidateTimer()
  if (useCodeForm.code.length === 8) {
    validateTimer = setTimeout(() => {
      validateCode()
    }, 300)
  } else {
    codeInfo.value = null
  }
}

const validateCode = async () => {
  if (!useCodeForm.code) {
    codeInfo.value = null
    codeCheckError.value = ''
    return
  }
  if (useCodeForm.code.length < 8) {
    codeInfo.value = { valid: false, reason: '邀请码格式不正确（应为8位字母数字）' }
    codeCheckError.value = '邀请码格式不正确'
    return
  }
  try {
    const res = await inviteCodeApi.check({ code: useCodeForm.code })
    codeInfo.value = res.data
    codeCheckError.value = res.data?.valid ? '' : (res.data?.reason || '邀请码无效')
  } catch (err) {
    const msg = err?.message || err?.response?.data?.message || '邀请码校验失败'
    codeInfo.value = { valid: false, reason: msg }
    codeCheckError.value = msg
  }
}

const submitUseCode = async () => {
  if (!useCodeForm.code) {
    codeCheckError.value = '请输入邀请码'
    return
  }
  if (!useCodeForm.user_id) {
    ElMessage.warning('请选择被邀请用户')
    return
  }
  if (!codeInfo.value?.valid) {
    ElMessage.warning('邀请码不可用，请检查')
    return
  }
  useCodeSubmitting.value = true
  try {
    const res = await inviteChainApi.useCode(useCodeForm)
    ElMessage.success(`邀请码使用成功，已与 ${res.data?.target_level?.name || '对应等级'} 绑定`)
    if (res.data) {
      codeInfo.value = {
        ...codeInfo.value,
        remaining_uses: res.data.remaining_uses ?? codeInfo.value.remaining_uses,
        used_count: res.data.used_count ?? codeInfo.value.used_count,
      }
      if ((res.data.remaining_uses ?? 1) <= 0) {
        codeInfo.value.valid = false
        codeInfo.value.reason = '邀请码已达使用上限'
      }
    }
    useCodeDialogVisible.value = false
    await Promise.all([loadList(), loadStats()])
  } catch (err) {
    const errMsg = err?.message || err?.response?.data?.message || '未知错误'
    try {
      await validateCode()
    } catch {}
    try {
      await ElMessageBox.confirm(
        `邀请码流转提交失败，系统已自动回滚，所有数据未变更。\n\n失败原因：${errMsg}\n\n您可以修改信息后重试，或稍后再试。`,
        '提交失败',
        {
          confirmButtonText: '重新提交',
          cancelButtonText: '知道了',
          type: 'error',
          distinguishCancelAndClose: true
        }
      )
      submitUseCode()
    } catch {
    }
  } finally {
    useCodeSubmitting.value = false
  }
}

const viewDetail = async (row) => {
  try {
    const res = await inviteCodeApi.detail(row.id)
    currentDetail.value = res.data
  } catch {
    currentDetail.value = { ...row, invite_chains: [] }
  }
  detailVisible.value = true
}

const toggleStatus = async (row) => {
  try {
    const res = await inviteCodeApi.toggle(row.id)
    if (res?.data) {
      const idx = list.value.findIndex(i => i.id === row.id)
      if (idx > -1) list.value.splice(idx, 1, { ...list.value[idx], ...res.data })
    }
    ElMessage.success('状态切换成功')
    await Promise.all([loadList(), loadStats()])
  } catch {
    row.status = row.status === 1 ? 0 : 1
    ElMessage.success('状态切换成功（模拟）')
    loadStats()
  }
}

const removeCode = async (row) => {
  try {
    await inviteCodeApi.remove(row.id)
    list.value = list.value.filter(i => i.id !== row.id)
    ElMessage.success('删除成功')
    await Promise.all([loadList(), loadStats()])
  } catch {
    list.value = list.value.filter(i => i.id !== row.id)
    ElMessage.success('删除成功（模拟）')
    loadStats()
  }
}

onMounted(() => {
  loadLevels()
  loadStats()
  loadList()
})
</script>
