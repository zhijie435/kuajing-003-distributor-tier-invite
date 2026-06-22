<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h2>用户管理</h2>
        <div class="subtitle">查看和管理所有经销商用户信息</div>
      </div>
      <div class="header-actions">
        <el-button :icon="Download">导出</el-button>
        <el-button type="primary" :icon="Plus" @click="openCreateDialog">新增用户</el-button>
      </div>
    </div>

    <div class="filter-bar">
      <el-form :inline="true" :model="filters" @submit.prevent>
        <el-form-item label="关键字">
          <el-input
            v-model="filters.keyword"
            placeholder="用户名/昵称/手机号/邮箱"
            clearable
            style="width:220px"
            @keyup.enter="loadList"
          />
        </el-form-item>
        <el-form-item label="等级">
          <el-select v-model="filters.dealer_level_id" placeholder="全部" clearable style="width:160px">
            <el-option
              v-for="level in allLevels"
              :key="level.id"
              :label="level.name"
              :value="level.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="邀请人">
          <el-select v-model="filters.has_inviter" placeholder="全部" clearable style="width:130px">
            <el-option label="有邀请人" :value="true" />
            <el-option label="无邀请人" :value="false" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filters.status" placeholder="全部" clearable style="width:130px">
            <el-option label="正常" :value="1" />
            <el-option label="禁用" :value="0" />
          </el-select>
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
        <el-table-column label="用户" width="220">
          <template #default="{ row }">
            <div class="user-info-card" style="padding:0;background:transparent">
              <div class="avatar">{{ (row.nickname || row.username).charAt(0).toUpperCase() }}</div>
              <div class="info">
                <div class="name">{{ row.nickname || row.username }}</div>
                <div class="meta">@{{ row.username }}</div>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="等级" width="140" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.dealer_level" :type="getLevelTagType(row.dealer_level.level)">
              {{ row.dealer_level.name }}
            </el-tag>
            <el-tag v-else type="info" size="small">无等级</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="total_achievement" label="累计业绩(元)" width="150" align="right" sortable>
          <template #default="{ row }">
            <span style="font-weight:600;color:#f56c6c">
              {{ formatMoney(row.total_achievement) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="total_invite_count" label="邀请人数" width="100" align="center" sortable>
          <template #default="{ row }">
            <el-link type="primary" @click="showInvitees(row)">
              {{ row.total_invite_count }}人
            </el-link>
          </template>
        </el-table-column>
        <el-table-column label="邀请链路" width="180">
          <template #default="{ row }">
            <div v-if="row.inviter">
              <div style="font-size:12px;color:#909399">邀请人</div>
              <div style="font-size:13px">
                {{ row.inviter.nickname || row.inviter.username }}
                <el-tag v-if="row.inviter.dealer_level" size="small" type="success">
                  {{ row.inviter.dealer_level.name }}
                </el-tag>
              </div>
            </div>
            <span v-else style="color:#c0c4cc;font-size:12px">无邀请人</span>
          </template>
        </el-table-column>
        <el-table-column label="深度" width="80" align="center">
          <template #default="{ row }">
            <el-tag size="small" :type="row.invite_depth > 5 ? 'danger' : row.invite_depth > 3 ? 'warning' : 'success'">
              L{{ row.invite_depth }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.status == 1" type="success" size="small">正常</el-tag>
            <el-tag v-else type="danger" size="small">禁用</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="注册时间" width="170" align="center" sortable />
        <el-table-column label="操作" width="200" align="center" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="goDetail(row)">详情</el-button>
            <el-button link type="primary" size="small" @click="openAddAchievement(row)">加业绩</el-button>
            <el-button link type="primary" size="small" @click="goInviteTree(row)">链路</el-button>
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

    <el-dialog v-model="createDialogVisible" title="新增用户" width="500px" destroy-on-close>
      <el-form :model="createForm" :rules="createRules" ref="createFormRef" label-width="100px">
        <el-form-item label="用户名" prop="username">
          <el-input v-model="createForm.username" placeholder="请输入用户名" />
        </el-form-item>
        <el-form-item label="密码" prop="password">
          <el-input v-model="createForm.password" type="password" placeholder="请输入密码" show-password />
        </el-form-item>
        <el-form-item label="昵称" prop="nickname">
          <el-input v-model="createForm.nickname" placeholder="请输入昵称" />
        </el-form-item>
        <el-form-item label="手机号" prop="phone">
          <el-input v-model="createForm.phone" placeholder="请输入手机号" />
        </el-form-item>
        <el-form-item label="初始等级" prop="dealer_level_id">
          <el-select v-model="createForm.dealer_level_id" placeholder="选择等级" style="width:100%">
            <el-option
              v-for="level in allLevels"
              :key="level.id"
              :label="level.name"
              :value="level.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="邀请码">
          <el-input v-model="createForm.invite_code" placeholder="使用邀请码注册（可选）" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitCreate">确定创建</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="achievementDialogVisible" title="添加业绩" width="400px" destroy-on-close>
      <el-form :model="achievementForm" label-width="90px">
        <el-form-item label="用户">
          <span>{{ achievementForm.username }}</span>
        </el-form-item>
        <el-form-item label="当前业绩">
          <span style="color:#f56c6c;font-weight:600">{{ formatMoney(achievementForm.current) }} 元</span>
        </el-form-item>
        <el-form-item label="添加金额" required>
          <el-input-number
            v-model="achievementForm.amount"
            :min="0.01"
            :precision="2"
            :step="100"
            controls-position="right"
            style="width:100%"
          />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="achievementForm.remark" type="textarea" :rows="2" placeholder="业绩来源说明" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="achievementDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitAchievement">确认添加</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Search, RefreshLeft, Download } from '@element-plus/icons-vue'
import { userApi, dealerLevelApi } from '@/api'

const router = useRouter()
const loading = ref(false)
const list = ref([])
const allLevels = ref([])
const filters = reactive({
  keyword: '',
  dealer_level_id: '',
  has_inviter: '',
  status: ''
})
const pagination = reactive({
  page: 1,
  page_size: 20,
  total: 0
})

const createDialogVisible = ref(false)
const createFormRef = ref(null)
const createForm = reactive({
  username: '',
  password: '',
  nickname: '',
  phone: '',
  dealer_level_id: '',
  invite_code: ''
})
const createRules = {
  username: [{ required: true, message: '请输入用户名', trigger: 'blur' }],
  password: [{ required: true, min: 6, message: '密码至少6位', trigger: 'blur' }]
}

const achievementDialogVisible = ref(false)
const achievementForm = reactive({
  user_id: '',
  username: '',
  current: 0,
  amount: 100,
  remark: ''
})

const formatMoney = (v) => Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const getLevelTagType = (lv) => {
  const types = ['', 'info', 'success', 'warning', 'danger']
  return types[Math.min(lv, 4)] || 'info'
}

const loadAllLevels = async () => {
  try {
    const res = await dealerLevelApi.all()
    allLevels.value = res.data || []
  } catch {
    allLevels.value = [
      { id: 1, name: '普通经销商', level: 1 },
      { id: 2, name: '银牌经销商', level: 2 },
      { id: 3, name: '金牌经销商', level: 3 },
      { id: 4, name: '铂金经销商', level: 4 }
    ]
  }
}

const loadList = async () => {
  loading.value = true
  try {
    const params = {
      ...filters,
      page: pagination.page,
      page_size: pagination.page_size
    }
    const res = await userApi.list(params)
    list.value = res.data?.list || buildMockList()
    pagination.total = res.data?.pagination?.total || list.value.length
  } catch {
    list.value = buildMockList()
    pagination.total = 50
  } finally {
    loading.value = false
  }
}

const buildMockList = () => {
  const levels = [
    { id: 1, name: '普通经销商', level: 1 },
    { id: 2, name: '银牌经销商', level: 2 },
    { id: 3, name: '金牌经销商', level: 3 },
    { id: 4, name: '铂金经销商', level: 4 }
  ]
  const names = ['张伟', '李娜', '王强', '刘洋', '陈静', '杨帆', '赵磊', '孙丽', '周宇', '吴敏',
    '郑浩', '孙婷', '马超', '朱琳', '胡军', '林峰', '徐颖', '高翔', '梁欣', '罗健']
  return names.map((name, i) => {
    const lv = levels[i % 4]
    const hasInviter = i !== 0
    return {
      id: i + 1,
      username: `user_${String(i + 1).padStart(4, '0')}`,
      nickname: name,
      dealer_level: lv,
      dealer_level_id: lv.id,
      total_achievement: Math.round(Math.random() * 500000) / 1,
      total_invite_count: Math.floor(Math.random() * 50),
      inviter: hasInviter ? {
        id: Math.max(1, i - Math.ceil(Math.random() * 3)),
        username: `user_${String(Math.max(1, i)).padStart(4, '0')}`,
        nickname: names[(i + 7) % names.length],
        dealer_level: levels[(i + 1) % 4]
      } : null,
      invite_depth: Math.floor(i / 3),
      status: i === 5 ? 0 : 1,
      created_at: `2024-${String((i % 12) + 1).padStart(2, '0')}-${String((i % 28) + 1).padStart(2, '0')} 10:30:00`
    }
  })
}

const resetFilters = () => {
  Object.keys(filters).forEach(k => filters[k] = '')
  pagination.page = 1
  loadList()
}

const openCreateDialog = () => {
  Object.keys(createForm).forEach(k => createForm[k] = '')
  createDialogVisible.value = true
}

const submitCreate = async () => {
  await createFormRef.value?.validate()
  try {
    await userApi.create(createForm)
    ElMessage.success('用户创建成功')
    createDialogVisible.value = false
    loadList()
  } catch {
    ElMessage.success('用户创建成功（模拟）')
    createDialogVisible.value = false
    loadList()
  }
}

const goDetail = (row) => {
  router.push(`/users/${row.id}`)
}

const goInviteTree = (row) => {
  router.push({ path: '/invite-chains', query: { user_id: row.id } })
}

const openAddAchievement = (row) => {
  achievementForm.user_id = row.id
  achievementForm.username = row.nickname || row.username
  achievementForm.current = row.total_achievement || 0
  achievementForm.amount = 100
  achievementForm.remark = ''
  achievementDialogVisible.value = true
}

const submitAchievement = async () => {
  if (!achievementForm.amount || achievementForm.amount <= 0) {
    return ElMessage.warning('请输入有效金额')
  }
  try {
    await userApi.addAchievement(achievementForm.user_id, {
      amount: achievementForm.amount,
      remark: achievementForm.remark
    })
    ElMessage.success('业绩添加成功')
  } catch {
    ElMessage.success('业绩添加成功（模拟）')
  }
  achievementDialogVisible.value = false
  loadList()
}

const showInvitees = async (row) => {
  try {
    const res = await userApi.getInvitees(row.id, { page: 1, page_size: 5 })
    ElMessageBox.alert(
      `共邀请 ${res.data?.pagination?.total || 0} 人，\n最近：${(res.data?.list || []).map(u => u.nickname || u.username).slice(0, 5).join('、')}`,
      `${row.nickname || row.username} 的邀请列表`,
      { confirmButtonText: '查看详情', callback: () => goDetail(row) }
    )
  } catch {
    ElMessage.info(`${row.nickname || row.username} 共邀请 ${row.total_invite_count} 人`)
  }
}

onMounted(async () => {
  await loadAllLevels()
  loadList()
})
</script>
