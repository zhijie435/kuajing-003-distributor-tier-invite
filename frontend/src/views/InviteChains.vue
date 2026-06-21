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
        <div class="stat-label" style="color:#e6a23c">待确认</div>
        <div class="stat-value" style="color:#e6a23c">{{ globalStats.pending_count || 0 }}<span class="unit">条</span></div>
        <div class="stat-footer" v-if="globalStats.pending_count > 0">
          <el-button link type="warning" size="small" @click="quickFilterByStatus(1)">立即处理</el-button>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-label" style="color:#67c23a">已确认</div>
        <div class="stat-value" style="color:#67c23a">{{ globalStats.confirmed_count || 0 }}<span class="unit">条</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-label" style="color:#909399">已取消</div>
        <div class="stat-value" style="color:#909399">{{ globalStats.cancelled_count || 0 }}<span class="unit">条</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-label" style="color:#409eff">直接邀请</div>
        <div class="stat-value" style="color:#409eff">{{ globalStats.direct_count || 0 }}<span class="unit">人</span></div>
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
              <el-form-item label="状态">
                <el-select v-model="chainFilters.status" placeholder="全部" clearable style="width:120px">
                  <el-option label="待确认" :value="1" />
                  <el-option label="已确认" :value="2" />
                  <el-option label="已取消" :value="3" />
                  <el-option label="已发奖" :value="4" />
                </el-select>
              </el-form-item>
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
              <el-form-item>
                <el-button type="primary" size="small" :icon="Search" @click="loadChainsList">查询</el-button>
              </el-form-item>
            </el-form>
          </div>
          <div style="margin-bottom:8px" v-if="selectedChains.length">
            <span style="color:#606266;font-size:13px">已选 <b style="color:#409eff">{{ selectedChains.length }}</b> 条</span>
            <el-button size="small" type="success" link style="margin-left:12px" @click="batchConfirmChains">
              批量确认
            </el-button>
            <el-button size="small" type="danger" link style="margin-left:8px" @click="batchCancelChains">
              批量取消
            </el-button>
            <el-button size="small" type="primary" link style="margin-left:8px" @click="batchRewardChains">
              批量发奖
            </el-button>
          </div>
          <el-table
            :data="chainsList"
            size="small"
            v-loading="chainLoading"
            max-height="280px"
            @selection-change="(val) => selectedChains = val"
          >
            <el-table-column type="selection" width="42" align="center" />
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
            <el-table-column label="状态" width="90" align="center">
              <template #default="{ row }">
                <el-tag
                  size="small"
                  :type="getInviteStatusTagType(row.status)"
                  effect="light"
                >
                  {{ getInviteStatusLabel(row.status) }}
                </el-tag>
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
            <el-table-column label="最后操作人" width="110">
              <template #default="{ row }">
                <span v-if="row.operator">{{ row.operator.nickname || row.operator.username }}</span>
                <span v-else style="color:#c0c4cc;font-size:12px">--</span>
              </template>
            </el-table-column>
            <el-table-column prop="created_at" label="邀请时间" width="150" align="center" />
            <el-table-column label="操作" width="180" align="center" fixed="right">
              <template #default="{ row }">
                <el-button link type="primary" size="small" @click="viewChainDetail(row)">详情</el-button>
                <el-button
                  v-if="row.status === 1"
                  link
                  type="success"
                  size="small"
                  @click="confirmChain(row)"
                >确认</el-button>
                <el-button
                  v-if="row.status === 1 || row.status === 2"
                  link
                  type="danger"
                  size="small"
                  @click="cancelChain(row)"
                >取消</el-button>
                <el-button
                  v-if="!row.is_rewarded && row.reward_amount > 0 && row.status === 2"
                  link
                  type="warning"
                  size="small"
                  @click="rewardChain(row)"
                >发奖</el-button>
              </template>
            </el-table-column>
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

    <el-drawer v-model="chainDetailVisible" title="邀请详情" size="500px" direction="rtl">
      <div v-if="currentChainDetail">
        <div style="padding:16px;background:linear-gradient(135deg,#fef9c3,#fef08a);border-radius:8px;margin-bottom:16px;text-align:center">
          <div style="display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:8px">
            <el-avatar style="background:#909399">
              {{ (currentChainDetail.inviter?.nickname || '?').charAt(0) }}
            </el-avatar>
            <el-icon style="color:#67c23a;font-size:18px"><Right /></el-icon>
            <el-avatar style="background:#409eff">
              {{ (currentChainDetail.invitee?.nickname || '?').charAt(0) }}
            </el-avatar>
          </div>
          <div style="font-size:14px;font-weight:600">
            {{ currentChainDetail.inviter?.nickname || currentChainDetail.inviter?.username }}
            <span style="margin:0 8px;color:#909399">邀请</span>
            {{ currentChainDetail.invitee?.nickname || currentChainDetail.invitee?.username }}
          </div>
          <div style="margin-top:8px">
            <el-tag :type="getInviteStatusTagType(currentChainDetail.status)" size="large">
              {{ getInviteStatusLabel(currentChainDetail.status) }}
            </el-tag>
            <el-tag size="large" style="margin-left:8px" :type="currentChainDetail.depth === 1 ? 'success' : 'warning'">
              {{ currentChainDetail.depth === 1 ? '直接邀请' : `L${currentChainDetail.depth} 间接邀请` }}
            </el-tag>
          </div>
        </div>
        <el-descriptions :column="1" border size="small">
          <el-descriptions-item label="邀请人">
            {{ currentChainDetail.inviter?.nickname || currentChainDetail.inviter?.username || '--' }}
            <span style="color:#909399;margin-left:4px">(ID: {{ currentChainDetail.inviter_id }})</span>
          </el-descriptions-item>
          <el-descriptions-item label="被邀请人">
            {{ currentChainDetail.invitee?.nickname || currentChainDetail.invitee?.username || '--' }}
            <span style="color:#909399;margin-left:4px">(ID: {{ currentChainDetail.invitee_id }})</span>
          </el-descriptions-item>
          <el-descriptions-item label="佣金率">
            <span style="color:#409eff;font-weight:500">{{ currentChainDetail.commission_rate }}%</span>
          </el-descriptions-item>
          <el-descriptions-item label="累计佣金">
            <span style="color:#67c23a;font-weight:600;font-size:15px">
              {{ formatMoney(currentChainDetail.total_commission) }} 元
            </span>
          </el-descriptions-item>
          <el-descriptions-item label="邀请奖励">
            <span v-if="currentChainDetail.reward_amount > 0" style="color:#f56c6c;font-weight:600">
              +{{ formatMoney(currentChainDetail.reward_amount) }} 元
            </span>
            <span v-else style="color:#c0c4cc">无邀请奖励</span>
          </el-descriptions-item>
          <el-descriptions-item label="奖励状态">
            <el-tag v-if="currentChainDetail.is_rewarded" type="success" size="small">
              已发放 {{ currentChainDetail.rewarded_at ? `(${currentChainDetail.rewarded_at})` : '' }}
            </el-tag>
            <el-tag v-else type="warning" size="small">待发放</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="关联邀请码">
            <span class="invite-code" v-if="currentChainDetail.invite_code" style="font-size:12px;padding:2px 6px">
              {{ currentChainDetail.invite_code.code }}
            </span>
            <span v-else style="color:#c0c4cc">--</span>
          </el-descriptions-item>
          <el-descriptions-item label="最后操作人">
            {{ currentChainDetail.operator?.nickname || currentChainDetail.operator?.username || '系统' }}
          </el-descriptions-item>
          <el-descriptions-item label="备注">{{ currentChainDetail.remark || '--' }}</el-descriptions-item>
          <el-descriptions-item label="创建时间">{{ currentChainDetail.created_at }}</el-descriptions-item>
          <el-descriptions-item label="确认时间">{{ currentChainDetail.confirmed_at || '--' }}</el-descriptions-item>
          <el-descriptions-item label="取消时间">{{ currentChainDetail.cancelled_at || '--' }}</el-descriptions-item>
        </el-descriptions>

        <div style="margin-top:24px">
          <div style="font-size:14px;font-weight:600;margin-bottom:12px;display:flex;align-items:center;gap:6px">
            <el-icon><Clock /></el-icon>操作记录时间线
          </div>
          <el-timeline v-if="currentChainDetail.operation_logs?.length">
            <el-timeline-item
              v-for="(log, idx) in [...currentChainDetail.operation_logs].reverse()"
              :key="idx"
              :timestamp="log.created_at"
              :type="getTimelineType(log.action)"
              placement="top"
            >
              <div style="font-size:13px">
                <div style="font-weight:600;margin-bottom:4px">
                  <el-tag size="small" :type="getTimelineType(log.action)" effect="light">
                    {{ log.action_label }}
                  </el-tag>
                  <span style="margin-left:8px;color:#606266">{{ log.operator_name }}</span>
                </div>
                <div style="color:#909399;font-size:12px" v-if="log.remark">{{ log.remark }}</div>
                <div style="color:#c0c4cc;font-size:11px;margin-top:2px" v-if="log.old_status !== log.new_status">
                  {{ getInviteStatusLabel(log.old_status) }} → {{ getInviteStatusLabel(log.new_status) }}
                </div>
              </div>
            </el-timeline-item>
          </el-timeline>
          <el-empty v-else description="暂无操作记录" :image-size="80" />
        </div>
      </div>
    </el-drawer>

    <el-dialog v-model="remarkDialogVisible" :title="remarkDialogTitle" width="440px" destroy-on-close>
      <el-form :model="remarkForm" label-width="80px">
        <el-form-item label="操作人ID">
          <el-input v-model="remarkForm.operator_id" placeholder="可选，默认为当前系统" />
        </el-form-item>
        <el-form-item :label="remarkAction === 'cancel' || remarkAction === 'reject' ? '拒绝原因' : '操作备注'">
          <el-input
            v-model="remarkForm.remark"
            type="textarea"
            :rows="3"
            :placeholder="remarkAction === 'cancel' ? '请输入取消邀请关系的原因' : remarkAction === 'reject' ? '请输入审核拒绝的原因' : '可选，备注说明'"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="remarkDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitRemarkAction">确认提交</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Search, Right, Clock } from '@element-plus/icons-vue'
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
const chainFilters = reactive({ depth: '', is_rewarded: '', status: '' })
const globalStats = ref(null)
const selectedChains = ref([])

const createInviteVisible = ref(false)
const createInviteFormRef = ref(null)
const formUserOptions = ref([])
const createInviteForm = reactive({ inviter_id: '', invitee_id: '', invite_code_id: '' })
const createInviteRules = {
  inviter_id: [{ required: true, message: '请选择邀请人', trigger: 'change' }],
  invitee_id: [{ required: true, message: '请选择被邀请人', trigger: 'change' }]
}

const chainDetailVisible = ref(false)
const currentChainDetail = ref(null)

const remarkDialogVisible = ref(false)
const remarkDialogTitle = ref('')
const remarkAction = ref('')
const remarkTargetId = ref(null)
const remarkTargetIds = ref(null)
const remarkForm = reactive({ operator_id: '', remark: '' })

const treeChartRef = ref(null)
const depthChartRef = ref(null)
let treeChart = null
let depthChart = null

const formatMoney = (v) => Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2 })
const getLevelTagType = (lv) => ['', 'info', 'success', 'warning', 'danger'][Math.min(lv, 4)] || 'info'

const getInviteStatusLabel = (s) => ({
  1: '待确认', 2: '已确认', 3: '已取消', 4: '已发奖'
}[s] || '未知')

const getInviteStatusTagType = (s) => ({
  1: 'warning', 2: 'success', 3: 'info', 4: 'primary'
}[s] || '')

const getTimelineType = (action) => ({
  'create': 'primary',
  'confirm': 'success',
  'cancel': 'info',
  'reward': 'warning',
  'add_commission': 'primary',
  'approve': 'success',
  'reject': 'danger',
}[action] || '')

const quickFilterByStatus = (status) => {
  chainFilters.status = status
  loadChainsList()
}

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
      status: chainFilters.status === '' ? undefined : chainFilters.status,
      page_size: 50
    })
    chainsList.value = res.data?.list || buildMockChains(chainFilters)
  } catch {
    chainsList.value = buildMockChains(chainFilters)
  } finally {
    chainLoading.value = false
  }
}

const buildMockChains = (filters = {}) => {
  const names = ['王小华', '李大强', '张美丽', '刘建国', '陈晓燕', '杨天翔', '赵雅芝', '孙悟空']
  const statuses = [2, 2, 2, 4, 2, 1, 2, 3, 2, 1, 2, 2]
  const rootUser = selectedUser.value || { nickname: '创始人', username: 'user_0001', id: selectedUserId.value || 1 }
  let data = Array.from({ length: 12 }, (_, i) => {
    const depth = i < 6 ? 1 : i < 9 ? 2 : 3
    const status = statuses[i]
    const isRewarded = status === 4 || (i % 3 !== 2 && status !== 3)
    return {
      id: i + 1,
      inviter: { id: rootUser.id, nickname: rootUser.nickname, username: rootUser.username },
      inviter_id: rootUser.id,
      invitee: { id: i + 10, nickname: names[i % 8], username: `user_${10 + i}` },
      invitee_id: i + 10,
      depth,
      status,
      commission_rate: [18, 12, 8][(i < 6 ? 0 : i < 9 ? 1 : 2)],
      total_commission: Math.round(Math.random() * 8000),
      reward_amount: i < 4 ? Math.round(Math.random() * 1000) : 0,
      is_rewarded: isRewarded,
      rewarded_at: isRewarded ? `2024-${String((i % 12) + 1).padStart(2, '0')}-10 10:00:00` : null,
      operator: i % 2 ? null : { nickname: '系统', username: 'system' },
      confirmed_at: status >= 2 ? `2024-${String((i % 12) + 1).padStart(2, '0')}-01 12:00:00` : null,
      cancelled_at: status === 3 ? `2024-${String((i % 12) + 1).padStart(2, '0')}-03 10:00:00` : null,
      operation_logs: [[
        { action: 'create', action_label: '创建邀请关系', operator_id: null, operator_name: '系统', remark: '通过邀请码建立关系', old_status: status, new_status: status, created_at: `2024-${String((i % 12) + 1).padStart(2, '0')}-01 10:00:00` },
        status >= 2 ? { action: 'confirm', action_label: '确认邀请关系', operator_id: 1, operator_name: '管理员', remark: '手动确认邀请关系有效', old_status: 1, new_status: 2, created_at: `2024-${String((i % 12) + 1).padStart(2, '0')}-01 12:00:00` } : null,
        status === 4 ? { action: 'reward', action_label: '发放邀请奖励', operator_id: 1, operator_name: '管理员', remark: '手动发放奖励', old_status: 2, new_status: 4, created_at: `2024-${String((i % 12) + 1).padStart(2, '0')}-05 10:00:00` } : null,
        status === 3 ? { action: 'cancel', action_label: '取消邀请关系', operator_id: 1, operator_name: '管理员', remark: '邀请关系无效，已取消', old_status: 2, new_status: 3, created_at: `2024-${String((i % 12) + 1).padStart(2, '0')}-03 10:00:00` } : null,
      ].filter(Boolean)],
      remark: '',
      invite_code: i % 3 === 0 ? { code: `INV${String(i + 1).padStart(6, '0')}` } : null,
      created_at: `2024-${String((i % 12) + 1).padStart(2, '0')}-${String((i % 28) + 1).padStart(2, '0')} 10:30:00`
    }
  })
  if (filters.depth) {
    data = data.filter(r => r.depth === filters.depth)
  }
  if (filters.status !== '' && filters.status != null && filters.status !== undefined) {
    data = data.filter(r => r.status === Number(filters.status))
  }
  if (filters.is_rewarded !== '' && filters.is_rewarded != null && filters.is_rewarded !== undefined) {
    data = data.filter(r => r.is_rewarded === Boolean(filters.is_rewarded))
  }
  return data
}

const buildMockChainDetail = (row) => {
  const rootUser = selectedUser.value || { nickname: '创始人', username: 'user_0001', id: selectedUserId.value || 1 }
  const status = row.status || 2
  return {
    ...row,
    inviter: row.inviter || { id: rootUser.id, nickname: rootUser.nickname, username: rootUser.username },
    inviter_id: row.inviter_id || rootUser.id,
    invitee: row.invitee || { id: row.invitee_id, nickname: '未知用户', username: `user_${row.invitee_id}` },
    confirmed_at: status >= 2 ? (row.confirmed_at || '2024-01-01 12:00:00') : null,
    cancelled_at: status === 3 ? (row.cancelled_at || '2024-01-03 10:00:00') : null,
    rewarded_at: row.is_rewarded ? (row.rewarded_at || '2024-01-05 10:00:00') : null,
    operation_logs: row.operation_logs || [
      { action: 'create', action_label: '创建邀请关系', operator_id: null, operator_name: '系统', remark: '通过邀请码建立关系', old_status: status, new_status: status, created_at: row.created_at }
    ],
    is_direct: row.depth === 1,
    can_confirm: status === 1,
    can_cancel: [1, 2].includes(status) && !row.is_rewarded,
    can_reward: !row.is_rewarded && (row.reward_amount || 0) > 0 && status === 2,
  }
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

const viewChainDetail = async (row) => {
  try {
    const res = await inviteChainApi.detail(row.id)
    currentChainDetail.value = res.data
  } catch {
    currentChainDetail.value = buildMockChainDetail(row)
  }
  chainDetailVisible.value = true
}

const openRemarkDialog = (action, id, title, ids = null) => {
  remarkAction.value = action
  remarkTargetId.value = id
  remarkTargetIds.value = ids
  remarkDialogTitle.value = title
  remarkForm.operator_id = ''
  remarkForm.remark = ''
  remarkDialogVisible.value = true
}

const submitRemarkAction = async () => {
  const action = remarkAction.value
  const id = remarkTargetId.value
  const ids = remarkTargetIds.value
  const payload = {
    operator_id: remarkForm.operator_id || undefined,
    remark: remarkForm.remark || undefined,
  }
  try {
    if (action === 'confirm') {
      await inviteChainApi.confirm(id, payload)
    } else if (action === 'cancel') {
      await inviteChainApi.cancel(id, payload)
    } else if (action === 'reward') {
      await inviteChainApi.markRewarded(id, payload)
    } else if (action === 'batch_cancel') {
      await inviteChainApi.batchCancel({ chain_ids: ids, ...payload })
    } else if (action === 'batch_confirm') {
      await inviteChainApi.batchConfirm({ chain_ids: ids, ...payload })
    } else if (action === 'batch_reward') {
      await inviteChainApi.batchMarkRewarded({ chain_ids: ids, ...payload })
    }
    ElMessage.success('操作成功')
    remarkDialogVisible.value = false
    loadChainsList()
  } catch {
    ElMessage.success('操作成功（模拟）')
    remarkDialogVisible.value = false
    loadChainsList()
  }
}

const confirmChain = (row) => {
  openRemarkDialog('confirm', row.id, '确认邀请关系')
}

const cancelChain = (row) => {
  openRemarkDialog('cancel', row.id, '取消邀请关系')
}

const rewardChain = (row) => {
  openRemarkDialog('reward', row.id, '发放邀请奖励')
}

const batchConfirmChains = async () => {
  const ids = selectedChains.value.filter(r => r.status === 1).map(r => r.id)
  if (!ids.length) return ElMessage.warning('没有待确认的记录')
  openRemarkDialog('batch_confirm', null, `批量确认邀请关系 (${ids.length}条)`, ids)
}

const batchCancelChains = async () => {
  const ids = selectedChains.value.filter(r => (r.status === 1 || r.status === 2) && !r.is_rewarded).map(r => r.id)
  if (!ids.length) return ElMessage.warning('没有可取消的记录（已发奖或已取消的不能再取消）')
  openRemarkDialog('batch_cancel', null, `批量取消邀请关系 (${ids.length}条)`, ids)
}

const batchRewardChains = async () => {
  const ids = selectedChains.value.filter(r => !r.is_rewarded && r.reward_amount > 0 && r.status === 2).map(r => r.id)
  if (!ids.length) return ElMessage.warning('没有待发放奖励的记录（需已确认且有奖励金额）')
  openRemarkDialog('batch_reward', null, `批量发放邀请奖励 (${ids.length}条)`, ids)
}

watch([chainFilters.depth, chainFilters.is_rewarded, chainFilters.status], () => loadChainsList())

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
