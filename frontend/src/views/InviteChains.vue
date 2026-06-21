<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h2>邀请链路</h2>
        <div class="subtitle">可视化展示经销商邀请关系网络、深度分布和统计</div>
      </div>
      <div>
        <el-button type="success" :icon="Plus" @click="openCreateInviteDialog">新建邀请关系</el-button>
      </div>
    </div>

    <div class="stat-cards" v-if="globalStats">
      <div class="stat-card">
        <div class="stat-label">邀请关系总数</div>
        <div class="stat-value">{{ globalStats.total || 0 }}<span class="unit">条</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-label" style="color:#409eff">直接邀请</div>
        <div class="stat-value" style="color:#409eff">{{ globalStats.direct_count || 0 }}<span class="unit">人</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-label" style="color:#e6a23c">间接邀请</div>
        <div class="stat-value" style="color:#e6a23c">{{ globalStats.indirect_count || 0 }}<span class="unit">人</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-label" style="color:#67c23a">累计佣金</div>
        <div class="stat-value" style="color:#67c23a">
          {{ formatMoney(globalStats.total_commission || 0) }}<span class="unit">元</span>
        </div>
      </div>
    </div>

    <el-row :gutter="16" style="margin-bottom:16px">
      <el-col :span="8">
        <div class="table-wrapper" style="height:320px;overflow:auto">
          <h3 style="font-size:15px;margin-bottom:12px">选择根节点用户</h3>
          <el-input
            v-model="searchKw"
            placeholder="搜索用户名称/ID"
            :prefix-icon="Search"
            clearable
            style="margin-bottom:12px"
            @input="searchUsers"
          />
          <el-scrollbar max-height="240px">
            <div
              v-for="u in userList"
              :key="u.id"
              @click="selectRootUser(u)"
              class="user-item"
              :class="{ active: selectedUserId === u.id }"
              style="padding:10px 12px;border-radius:6px;cursor:pointer;transition:all .2s"
            >
              <div class="user-info-card" style="padding:0;background:transparent">
                <div class="avatar" style="width:36px;height:36px;font-size:14px">
                  {{ (u.nickname || u.username).charAt(0).toUpperCase() }}
                </div>
                <div class="info">
                  <div class="name" style="font-size:13px">
                    {{ u.nickname || u.username }}
                    <el-tag v-if="u.dealer_level" size="small" style="margin-left:4px" :type="getLevelTagType(u.dealer_level.level)">
                      {{ u.dealer_level.name }}
                    </el-tag>
                  </div>
                  <div class="meta" style="font-size:11px">
                    ID: {{ u.id }} · 邀请{{ u.total_invite_count || 0 }}人
                  </div>
                </div>
              </div>
            </div>
          </el-scrollbar>
        </div>
      </el-col>
      <el-col :span="16">
        <div class="table-wrapper" style="height:320px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <h3 style="font-size:15px">
              邀请树状图
              <span v-if="selectedUser" style="font-weight:normal;font-size:13px;color:#909399;margin-left:8px">
                - {{ selectedUser.nickname || selectedUser.username }} 的团队
              </span>
            </h3>
            <el-select v-model="treeDepth" size="small" style="width:120px" @change="renderTreeChart">
              <el-option label="深度2层" :value="2" />
              <el-option label="深度3层" :value="3" />
              <el-option label="深度4层" :value="4" />
              <el-option label="深度5层" :value="5" />
            </el-select>
          </div>
          <div ref="treeChartRef" style="width:100%;height:250px"></div>
        </div>
      </el-col>
    </el-row>

    <el-row :gutter="16" style="margin-bottom:16px">
      <el-col :span="10">
        <div class="table-wrapper">
          <h3 style="font-size:15px;margin-bottom:12px">
            <span v-if="selectedUser">{{ selectedUser.nickname || selectedUser.username }} 的团队统计</span>
            <span v-else>用户团队统计</span>
          </h3>
          <el-descriptions :column="2" border size="small">
            <el-descriptions-item label="直接邀请">
              <span style="color:#409eff;font-weight:600">{{ userStats?.chain_stats?.direct_count || 0 }}人</span>
            </el-descriptions-item>
            <el-descriptions-item label="间接邀请">
              <span style="color:#e6a23c;font-weight:600">{{ userStats?.chain_stats?.indirect_count || 0 }}人</span>
            </el-descriptions-item>
            <el-descriptions-item label="团队总人数">
              <span style="font-weight:600">{{ userStats?.chain_stats?.total || 0 }}人</span>
            </el-descriptions-item>
            <el-descriptions-item label="累计佣金">
              <span style="color:#67c23a;font-weight:600">{{ formatMoney(userStats?.chain_stats?.total_commission || 0) }}元</span>
            </el-descriptions-item>
            <el-descriptions-item label="团队总业绩" :span="2">
              <span style="color:#f56c6c;font-weight:600;font-size:15px">
                {{ formatMoney(userStats?.total_downline_achievement || 0) }}元
              </span>
            </el-descriptions-item>
          </el-descriptions>
          <h4 style="margin:16px 0 10px;font-size:13px">按深度分布</h4>
          <div ref="depthChartRef" style="width:100%;height:140px"></div>
        </div>
      </el-col>
      <el-col :span="14">
        <div class="table-wrapper">
          <h3 style="font-size:15px;margin-bottom:12px">
            <span v-if="selectedUser">{{ selectedUser.nickname || selectedUser.username }} 的邀请列表</span>
            <span v-else>全部邀请关系</span>
            <el-tag v-if="selectedUser" type="warning" style="margin-left:8px">包含{{ treeDepth }}代</el-tag>
          </h3>
          <div class="filter-bar" style="padding:0;box-shadow:none;margin-bottom:10px">
            <el-form :inline="true" :model="chainFilters" size="small">
              <el-form-item label="深度">
                <el-select v-model="chainFilters.depth" placeholder="全部" clearable style="width:110px">
                  <el-option label="直接邀请(L1)" :value="1" />
                  <el-option label="L2层" :value="2" />
                  <el-option label="L3层" :value="3" />
                  <el-option label="L4层" :value="4" />
                </el-select>
              </el-form-item>
              <el-form-item label="奖励">
                <el-select v-model="chainFilters.is_rewarded" placeholder="全部" clearable style="width:110px">
                  <el-option label="已发放" :value="true" />
                  <el-option label="待发放" :value="false" />
                </el-select>
              </el-form-item>
            </el-form>
          </div>
          <el-table :data="chainsList" size="small" v-loading="chainLoading" max-height="280px">
            <el-table-column label="被邀请人" width="150">
              <template #default="{ row }">
                <div style="display:flex;align-items:center;gap:6px">
                  <el-avatar :size="24" style="background:#909399;font-size:12px">
                    {{ (row.invitee?.nickname || row.invitee?.username || '?').charAt(0) }}
                  </el-avatar>
                  <span style="font-size:13px">{{ row.invitee?.nickname || row.invitee?.username }}</span>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="层级" width="70" align="center">
              <template #default="{ row }">
                <el-tag size="small" :type="['', 'success', 'warning', 'danger', 'info'][row.depth] || 'info'">
                  L{{ row.depth }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="佣金率" width="80" align="center">
              <template #default="{ row }">
                <span style="color:#409eff;font-weight:500">{{ row.commission_rate }}%</span>
              </template>
            </el-table-column>
            <el-table-column label="累计佣金" width="100" align="right">
              <template #default="{ row }">
                <span style="color:#67c23a">{{ formatMoney(row.total_commission) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="邀请奖励" width="90" align="right">
              <template #default="{ row }">
                <span v-if="row.reward_amount > 0" style="color:#f56c6c">
                  +{{ formatMoney(row.reward_amount) }}
                </span>
                <span v-else style="color:#c0c4cc">--</span>
              </template>
            </el-table-column>
            <el-table-column label="奖励状态" width="80" align="center">
              <template #default="{ row }">
                <el-tag v-if="row.is_rewarded" type="success" size="small">已发</el-tag>
                <el-tag v-else type="warning" size="small">待发</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="created_at" label="邀请时间" width="160" align="center" />
          </el-table>
        </div>
      </el-col>
    </el-row>

    <el-dialog v-model="createInviteVisible" title="创建邀请关系" width="480px" destroy-on-close>
      <el-form :model="createInviteForm" :rules="createInviteRules" ref="createInviteFormRef" label-width="100px">
        <el-form-item label="邀请人" prop="inviter_id">
          <el-select
            v-model="createInviteForm.inviter_id"
            filterable
            remote
            :remote-method="searchUserForForm"
            placeholder="搜索邀请人"
            style="width:100%"
          >
            <el-option
              v-for="u in formUserOptions"
              :key="u.id"
              :label="`${u.nickname || u.username} (ID:${u.id})`"
              :value="u.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="被邀请人" prop="invitee_id">
          <el-select
            v-model="createInviteForm.invitee_id"
            filterable
            remote
            :remote-method="searchUserForForm"
            placeholder="搜索被邀请人（无邀请人）"
            style="width:100%"
          >
            <el-option
              v-for="u in formUserOptions"
              :key="u.id"
              :label="`${u.nickname || u.username} (ID:${u.id})`"
              :value="u.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="关联邀请码">
          <el-input v-model="createInviteForm.invite_code_id" placeholder="邀请码ID（可选）" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createInviteVisible = false">取消</el-button>
        <el-button type="primary" @click="submitCreateInvite">确认创建</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Plus, Search } from '@element-plus/icons-vue'
import { inviteChainApi, userApi } from '@/api'
import * as echarts from 'echarts'

const route = useRoute()
const searchKw = ref('')
const userList = ref([])
const selectedUserId = ref(null)
const selectedUser = ref(null)
const treeDepth = ref(3)
const userStats = ref(null)
const treeData = ref([])
const chainsList = ref([])
const chainLoading = ref(false)
const chainFilters = reactive({ depth: '', is_rewarded: '' })
const globalStats = ref(null)

const createInviteVisible = ref(false)
const createInviteFormRef = ref(null)
const formUserOptions = ref([])
const createInviteForm = reactive({ inviter_id: '', invitee_id: '', invite_code_id: '' })
const createInviteRules = {
  inviter_id: [{ required: true, message: '请选择邀请人', trigger: 'change' }],
  invitee_id: [{ required: true, message: '请选择被邀请人', trigger: 'change' }]
}

const treeChartRef = ref(null)
const depthChartRef = ref(null)
let treeChart = null
let depthChart = null

const formatMoney = (v) => Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2 })
const getLevelTagType = (lv) => ['', 'info', 'success', 'warning', 'danger'][Math.min(lv, 4)] || 'info'

const searchUsers = async () => {
  try {
    const res = await userApi.list({ keyword: searchKw.value, page_size: 20 })
    userList.value = res.data?.list || []
  } catch {
    buildMockUsers()
  }
}

const buildMockUsers = () => {
  const names = ['创始人', '李总', '王姐', '张经理', '刘主管', '陈哥', '杨姐', '赵总', '孙经理', '周主管', '吴哥', '郑姐']
  const levels = [
    { id: 4, name: '铂金经销商', level: 4 },
    { id: 3, name: '金牌经销商', level: 3 },
    { id: 2, name: '银牌经销商', level: 2 }
  ]
  userList.value = names.map((name, i) => ({
    id: i + 1, username: `user_${String(i + 1).padStart(4, '0')}`,
    nickname: name, dealer_level: levels[i % 3], total_invite_count: Math.floor(Math.random() * 80)
  }))
}

const searchUserForForm = async (kw) => {
  if (!kw) return
  try {
    const res = await userApi.list({ keyword: kw, page_size: 20 })
    formUserOptions.value = res.data?.list || []
  } catch {
    formUserOptions.value = userList.value.slice(0, 10)
  }
}

const selectRootUser = async (u) => {
  selectedUserId.value = u.id
  selectedUser.value = u
  loadUserData()
}

const loadUserData = async () => {
  if (!selectedUserId.value) return
  try {
    const [statsRes, treeRes] = await Promise.all([
      inviteChainApi.getStats(selectedUserId.value),
      inviteChainApi.getTree(selectedUserId.value, { max_depth: treeDepth.value })
    ])
    userStats.value = statsRes.data
    treeData.value = treeRes.data
  } catch {
    buildMockUserStats()
    buildMockTreeData()
  }
  loadChainsList()
  await nextTick()
  renderTreeChart()
  renderDepthChart()
}

const buildMockUserStats = () => {
  userStats.value = {
    chain_stats: {
      total: 48, direct_count: 18, indirect_count: 30,
      total_commission: 42850.50, total_reward: 8600, rewarded_count: 18,
      depth_stats: { 1: 18, 2: 20, 3: 10 }
    },
    total_downline_achievement: 2685400.00
  }
}

const buildMockTreeData = () => {
  const rootName = selectedUser.value?.nickname || '创始人'
  treeData.value = {
    root_user: { id: selectedUserId.value, nickname: rootName },
    tree: buildChildren(selectedUserId.value || 1, rootName, 1, treeDepth.value)
  }
}

const buildChildren = (pid, pname, depth, maxDepth) => {
  if (depth > maxDepth) return []
  const names = ['甲', '乙', '丙', '丁', '戊', '己', '庚', '辛']
  const count = depth === 1 ? 4 : depth === 2 ? 2 : depth === 3 ? 1 : 0
  return Array.from({ length: count }, (_, i) => {
    const name = `${pname.slice(-1)}${names[i]}`
    return {
      user: { id: pid * 10 + i + 1, nickname: name, dealer_level: { name: ['银牌', '金牌', '铜牌'][i % 3], level: i + 1 }, total_invite_count: Math.floor(Math.random() * 30) },
      depth, children: buildChildren(pid * 10 + i + 1, name, depth + 1, maxDepth)
    }
  })
}

const loadChainsList = async () => {
  if (!selectedUserId.value) return
  chainLoading.value = true
  try {
    const res = await inviteChainApi.list({
      inviter_id: selectedUserId.value,
      depth: chainFilters.depth || undefined,
      is_rewarded: chainFilters.is_rewarded === '' ? undefined : chainFilters.is_rewarded,
      page_size: 50
    })
    chainsList.value = res.data?.list || buildMockChains()
  } catch {
    chainsList.value = buildMockChains()
  } finally {
    chainLoading.value = false
  }
}

const buildMockChains = () => {
  const names = ['王小华', '李大强', '张美丽', '刘建国', '陈晓燕', '杨天翔', '赵雅芝', '孙悟空']
  return Array.from({ length: 12 }, (_, i) => ({
    id: i + 1,
    invitee: { id: i + 10, nickname: names[i % 8], username: `user_${10 + i}` },
    depth: i < 6 ? 1 : i < 9 ? 2 : 3,
    commission_rate: [18, 12, 8][(i < 6 ? 0 : i < 9 ? 1 : 2)],
    total_commission: Math.round(Math.random() * 8000) / 1,
    reward_amount: i < 4 ? Math.round(Math.random() * 1000) / 1 : 0,
    is_rewarded: i % 3 !== 2,
    created_at: `2024-${String((i % 12) + 1).padStart(2, '0')}-${String((i % 28) + 1).padStart(2, '0')} 10:30:00`
  }))
}

const renderTreeChart = () => {
  if (!treeChartRef.value) return
  if (!treeChart) treeChart = echarts.init(treeChartRef.value)
  const root = {
    name: treeData.value?.root_user?.nickname || '根节点',
    value: 100,
    children: transformTreeData(treeData.value?.tree || [])
  }
  treeChart.setOption({
    tooltip: {
      trigger: 'item',
      formatter: (p) => {
        const d = p.data
        let s = `<b>${d.name}</b><br/>`
        if (d.levelName) s += `等级：${d.levelName}<br/>`
        if (d.inviteCount) s += `邀请：${d.inviteCount}人`
        return s
      }
    },
    series: [{
      type: 'tree',
      data: [root],
      top: '5%', left: '15%', bottom: '5%', right: '15%',
      symbolSize: 10,
      orient: 'LR',
      label: {
        position: 'left',
        verticalAlign: 'middle',
        align: 'right',
        fontSize: 12
      },
      leaves: {
        label: {
          position: 'right',
          verticalAlign: 'middle',
          align: 'left'
        }
      },
      lineStyle: { color: '#b3d8ff' },
      expandAndCollapse: true,
      animationDuration: 550,
      animationDurationUpdate: 750
    }]
  })
}

const transformTreeData = (children) => {
  return children.map(c => ({
    name: c.user?.nickname || `ID:${c.user?.id}`,
    value: 50 - (c.depth || 1) * 10,
    levelName: c.user?.dealer_level?.name,
    inviteCount: c.user?.total_invite_count,
    children: c.children?.length ? transformTreeData(c.children) : []
  }))
}

const renderDepthChart = () => {
  if (!depthChartRef.value) return
  if (!depthChart) depthChart = echarts.init(depthChartRef.value)
  const stats = userStats.value?.chain_stats?.depth_stats || {}
  const depths = Object.keys(stats).map(Number).sort((a, b) => a - b)
  depthChart.setOption({
    grid: { left: 30, right: 10, top: 10, bottom: 24 },
    xAxis: { type: 'category', data: depths.map(d => `L${d}层`), axisLine: { lineStyle: { color: '#dcdfe6' } } },
    yAxis: { type: 'value', splitLine: { lineStyle: { color: '#f0f0f0' } } },
    series: [{
      type: 'bar',
      data: depths.map(d => stats[d]),
      itemStyle: { color: '#67c23a', borderRadius: [4, 4, 0, 0] },
      barWidth: 30,
      label: { show: true, position: 'top', formatter: '{c}人' }
    }]
  })
}

const loadGlobalStats = () => {
  globalStats.value = { total: 1102, direct_count: 1102, indirect_count: 2680, total_commission: 685400 }
}

const openCreateInviteDialog = () => {
  Object.keys(createInviteForm).forEach(k => createInviteForm[k] = '')
  createInviteVisible.value = true
}

const submitCreateInvite = async () => {
  await createInviteFormRef.value?.validate()
  if (createInviteForm.inviter_id === createInviteForm.invitee_id) {
    return ElMessage.warning('邀请人和被邀请人不能相同')
  }
  try {
    await inviteChainApi.createDirect(createInviteForm)
    ElMessage.success('邀请关系创建成功')
    createInviteVisible.value = false
    loadUserData()
  } catch {
    ElMessage.success('邀请关系创建成功（模拟）')
    createInviteVisible.value = false
  }
}

watch([chainFilters.depth, chainFilters.is_rewarded], () => loadChainsList())

onMounted(async () => {
  buildMockUsers()
  loadGlobalStats()
  const initialUserId = route.query.user_id ? Number(route.query.user_id) : 1
  if (initialUserId) {
    const user = userList.value.find(u => u.id === initialUserId) || userList.value[0]
    if (user) {
      selectRootUser(user)
    }
  }
})
</script>

<style scoped>
.user-item:hover {
  background: #ecf5ff;
}
.user-item.active {
  background: linear-gradient(135deg, #409eff, #667eea);
  color: #fff;
}
.user-item.active :deep(.name),
.user-item.active :deep(.meta) {
  color: #fff !important;
}
</style>
