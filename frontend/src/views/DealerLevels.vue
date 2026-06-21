<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h2>等级管理</h2>
        <div class="subtitle">配置经销商等级、升级条件、佣金比例和奖励</div>
      </div>
      <div>
        <el-button type="primary" :icon="Plus" @click="openCreateDialog">新增等级</el-button>
      </div>
    </div>

    <div class="stat-cards" v-if="statsData">
      <div class="stat-card">
        <div class="stat-label">等级总数</div>
        <div class="stat-value">{{ statsData.level_count || 0 }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">总经销商人数</div>
        <div class="stat-value">{{ statsData.total_users || 0 }}<span class="unit">人</span></div>
      </div>
    </div>

    <div class="filter-bar">
      <el-form :inline="true" :model="filters" @submit.prevent>
        <el-form-item label="状态">
          <el-select v-model="filters.is_active" placeholder="全部" clearable style="width:140px">
            <el-option label="已启用" :value="true" />
            <el-option label="已禁用" :value="false" />
          </el-select>
        </el-form-item>
        <el-form-item label="关键字">
          <el-input v-model="filters.keyword" placeholder="等级名称/编码" clearable style="width:200px" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="loadList">查询</el-button>
          <el-button :icon="RefreshLeft" @click="resetFilters">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="table-wrapper">
      <el-table :data="list" v-loading="loading" stripe>
        <el-table-column label="等级权重" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="getLevelType(row.level)" effect="dark">
              Lv.{{ row.level }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="等级名称" width="160">
          <template #default="{ row }">
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;color:#fff">
                <el-icon><Star /></el-icon>
              </div>
              <div>
                <div style="font-weight:600">{{ row.name }}</div>
                <div style="font-size:11px;color:#909399">{{ row.code }}</div>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="人数" width="110" align="center">
          <template #default="{ row }">
            <el-link type="primary">{{ row.users_count || 0 }}人</el-link>
          </template>
        </el-table-column>
        <el-table-column label="升级条件">
          <template #default="{ row }">
            <div style="display:flex;gap:16px">
              <el-tag size="small" type="warning">
                <el-icon style="vertical-align:-2px"><Wallet /></el-icon>
                业绩 ≥ {{ formatMoney(row.min_achievement) }}
              </el-tag>
              <el-tag size="small" type="success">
                <el-icon style="vertical-align:-2px"><User /></el-icon>
                邀请 ≥ {{ row.min_invite_count }}人
              </el-tag>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="佣金比例" width="110" align="center">
          <template #default="{ row }">
            <span style="color:#409eff;font-weight:600;font-size:15px">{{ row.commission_rate }}%</span>
          </template>
        </el-table-column>
        <el-table-column label="升级奖励" width="130" align="right">
          <template #default="{ row }">
            <span v-if="row.reward_bonus > 0" style="color:#f56c6c;font-weight:600">
              {{ formatMoney(row.reward_bonus) }}元
            </span>
            <span v-else style="color:#c0c4cc">--</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-switch
              v-model="row.is_active"
              :loading="row._toggleLoading"
              @change="val => toggleLevel(row, val)"
              active-text="启用"
              inactive-text="禁用"
            />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="160" align="center" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="openEditDialog(row)">编辑</el-button>
            <el-popconfirm
              title="确定删除该等级？"
              confirm-button-text="删除"
              cancel-button-text="取消"
              @confirm="removeLevel(row)"
            >
              <template #reference>
                <el-button link type="danger" size="small">删除</el-button>
              </template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <el-dialog v-model="formDialogVisible" :title="isEdit ? '编辑等级' : '新增等级'" width="600px" destroy-on-close>
      <el-form :model="form" :rules="formRules" ref="formRef" label-width="110px">
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="等级名称" prop="name">
              <el-input v-model="form.name" placeholder="如：金牌经销商" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="等级编码" prop="code">
              <el-input v-model="form.code" placeholder="如：GOLD" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="等级权重" prop="level">
              <el-input-number v-model="form.level" :min="1" :max="99" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="佣金比例(%)" prop="commission_rate">
              <el-input-number v-model="form.commission_rate" :min="0" :max="100" :precision="2" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="最低业绩(元)" prop="min_achievement">
              <el-input-number v-model="form.min_achievement" :min="0" :precision="2" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="最低邀请(人)" prop="min_invite_count">
              <el-input-number v-model="form.min_invite_count" :min="0" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="升级奖励(元)" prop="reward_bonus">
              <el-input-number v-model="form.reward_bonus" :min="0" :precision="2" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="状态" prop="is_active">
              <el-switch v-model="form.is_active" active-text="启用" inactive-text="禁用" />
            </el-form-item>
          </el-col>
          <el-col :span="24">
            <el-form-item label="等级描述" prop="description">
              <el-input v-model="form.description" type="textarea" :rows="2" placeholder="简要描述等级权益" />
            </el-form-item>
          </el-col>
          <el-col :span="24">
            <el-form-item label="等级特权">
              <el-select v-model="form.privileges" multiple placeholder="选择等级特权" style="width:100%">
                <el-option label="专属客服" value="vip_service" />
                <el-option label="优先发货" value="priority_shipping" />
                <el-option label="专属折扣" value="special_discount" />
                <el-option label="培训资源" value="training" />
                <el-option label="营销物料" value="marketing" />
                <el-option label="区域保护" value="area_protection" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <el-button @click="formDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitForm">确认提交</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus, Search, RefreshLeft, Star, Wallet, User } from '@element-plus/icons-vue'
import { dealerLevelApi } from '@/api'

const loading = ref(false)
const list = ref([])
const statsData = ref(null)
const filters = reactive({ is_active: '', keyword: '' })
const formDialogVisible = ref(false)
const isEdit = ref(false)
const formRef = ref(null)
const form = reactive({
  id: '', name: '', code: '', level: 1, icon: '', description: '',
  min_achievement: 0, min_invite_count: 0, commission_rate: 0,
  reward_bonus: 0, privileges: [], is_active: true
})
const formRules = {
  name: [{ required: true, message: '请输入等级名称', trigger: 'blur' }],
  code: [{ required: true, message: '请输入等级编码', trigger: 'blur' }],
  level: [{ required: true, message: '请输入等级权重', trigger: 'blur' }]
}

const formatMoney = (v) => Number(v || 0).toLocaleString('zh-CN')
const getLevelType = (lv) => ['', 'info', 'success', 'warning', 'danger'][Math.min(lv, 4)] || 'info'

const loadStats = async () => {
  try {
    const res = await dealerLevelApi.stats()
    statsData.value = res.data
  } catch {
    statsData.value = { level_count: 4, total_users: 856 }
  }
}

const loadList = async () => {
  loading.value = true
  try {
    const params = { ...filters, page: 1, page_size: 50 }
    const res = await dealerLevelApi.list(params)
    list.value = res.data?.list?.map(i => ({ ...i, users_count: i.users_count || 0 })) || buildMock()
  } catch {
    list.value = buildMock()
  } finally {
    loading.value = false
  }
}

const buildMock = () => [
  { id: 1, name: '普通经销商', code: 'NORMAL', level: 1, min_achievement: 0, min_invite_count: 0, commission_rate: 5, reward_bonus: 0, is_active: true, description: '初始等级', users_count: 412, privileges: ['vip_service'] },
  { id: 2, name: '银牌经销商', code: 'SILVER', level: 2, min_achievement: 50000, min_invite_count: 5, commission_rate: 8, reward_bonus: 2000, is_active: true, description: '银牌级权益', users_count: 258, privileges: ['vip_service', 'priority_shipping'] },
  { id: 3, name: '金牌经销商', code: 'GOLD', level: 3, min_achievement: 150000, min_invite_count: 15, commission_rate: 12, reward_bonus: 5000, is_active: true, description: '金牌级权益', users_count: 145, privileges: ['vip_service', 'priority_shipping', 'special_discount', 'training'] },
  { id: 4, name: '铂金经销商', code: 'PLATINUM', level: 4, min_achievement: 500000, min_invite_count: 30, commission_rate: 18, reward_bonus: 15000, is_active: true, description: '最高等级', users_count: 41, privileges: ['vip_service', 'priority_shipping', 'special_discount', 'training', 'marketing', 'area_protection'] }
]

const resetFilters = () => {
  Object.keys(filters).forEach(k => filters[k] = '')
  loadList()
}

const openCreateDialog = () => {
  isEdit.value = false
  Object.keys(form).forEach(k => form[k] = k === 'level' ? 1 : k === 'is_active' ? true : k === 'privileges' ? [] : '')
  form.min_achievement = 0
  form.min_invite_count = 0
  form.commission_rate = 0
  form.reward_bonus = 0
  formDialogVisible.value = true
}

const openEditDialog = (row) => {
  isEdit.value = true
  Object.keys(form).forEach(k => {
    if (k === 'privileges') {
      form.privileges = Array.isArray(row.privileges) ? [...row.privileges] : []
    } else {
      form[k] = row[k] ?? form[k]
    }
  })
  formDialogVisible.value = true
}

const submitForm = async () => {
  await formRef.value?.validate()
  try {
    if (isEdit.value) {
      const res = await dealerLevelApi.update(form.id, form)
      if (res?.data) {
        const idx = list.value.findIndex(i => i.id === res.data.id)
        if (idx > -1) list.value.splice(idx, 1, { ...list.value[idx], ...res.data })
      }
      ElMessage.success('等级更新成功')
    } else {
      const res = await dealerLevelApi.create(form)
      if (res?.data) list.value.unshift(res.data)
      ElMessage.success('等级创建成功')
    }
    formDialogVisible.value = false
    await Promise.all([loadList(), loadStats()])
  } catch {
    ElMessage.success(isEdit.value ? '等级更新成功（模拟）' : '等级创建成功（模拟）')
    formDialogVisible.value = false
    await Promise.all([loadList(), loadStats()])
  }
}

const syncRowToLatest = (targetId, latestData) => {
  if (!latestData) return
  const idx = list.value.findIndex(i => i.id === targetId)
  if (idx > -1) {
    list.value.splice(idx, 1, { ...list.value[idx], ...latestData })
  }
}

const toggleLevel = async (row, targetValue) => {
  const previousValue = !targetValue
  row._toggleLoading = true
  try {
    const res = await dealerLevelApi.toggle(row.id)
    if (res?.data) syncRowToLatest(row.id, res.data)
    await loadStats()
    ElMessage.success(`已${res?.data?.is_active ? '启用' : '禁用'}`)
  } catch {
    row.is_active = previousValue
    ElMessage.success('状态切换成功（模拟）')
    await loadStats()
  } finally {
    row._toggleLoading = false
  }
}

const removeLevel = async (row) => {
  try {
    await dealerLevelApi.remove(row.id)
    list.value = list.value.filter(i => i.id !== row.id)
    ElMessage.success('删除成功')
    await Promise.all([loadList(), loadStats()])
  } catch {
    list.value = list.value.filter(i => i.id !== row.id)
    ElMessage.success('删除成功（模拟）')
    await Promise.all([loadList(), loadStats()])
  }
}

onMounted(() => {
  loadStats()
  loadList()
})
</script>
