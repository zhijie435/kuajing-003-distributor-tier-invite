<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h2>用户详情</h2>
        <div class="subtitle">经销商用户完整信息、等级进度和邀请链路</div>
      </div>
      <div>
        <el-button @click="$router.back()">返回列表</el-button>
      </div>
    </div>

    <el-row :gutter="16" style="margin-bottom:16px" v-if="detail">
      <el-col :span="8">
        <div class="table-wrapper">
          <div style="text-align:center;padding:20px 0 16px">
            <el-avatar :size="72" style="background:#409eff;font-size:28px">
              {{ (detail.user.nickname || detail.user.username).charAt(0).toUpperCase() }}
            </el-avatar>
            <h3 style="margin-top:12px">
              {{ detail.user.nickname || detail.user.username }}
              <el-tag
                v-if="detail.dealer_level"
                :type="getLevelTagType(detail.dealer_level.level)"
                style="margin-left:8px"
              >
                {{ detail.dealer_level.name }}
              </el-tag>
            </h3>
            <div style="color:#909399;font-size:13px;margin-top:4px">
              @{{ detail.user.username }}
              <span v-if="detail.user.phone"> · {{ detail.user.phone }}</span>
            </div>
          </div>
          <el-descriptions :column="1" border size="small">
            <el-descriptions-item label="累计业绩">
              <span style="color:#f56c6c;font-weight:600">{{ formatMoney(detail.user.total_achievement) }} 元</span>
            </el-descriptions-item>
            <el-descriptions-item label="当月业绩">
              {{ formatMoney(detail.user.current_month_achievement) }} 元
            </el-descriptions-item>
            <el-descriptions-item label="邀请人数">{{ detail.user.total_invite_count }} 人</el-descriptions-item>
            <el-descriptions-item label="邀请深度">L{{ detail.user.invite_depth }}</el-descriptions-item>
            <el-descriptions-item label="状态">
              <el-tag :type="detail.user.status == 1 ? 'success' : 'danger'" size="small">
                {{ detail.user.status == 1 ? '正常' : '禁用' }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="邀请人">
              <div v-if="detail.inviter" style="line-height:1.5">
                {{ detail.inviter.nickname || detail.inviter.username }}
                <el-tag v-if="detail.inviter.dealer_level" size="small" type="success">
                  {{ detail.inviter.dealer_level.name }}
                </el-tag>
              </div>
              <span v-else style="color:#c0c4cc">无邀请人</span>
            </el-descriptions-item>
            <el-descriptions-item label="注册时间">{{ detail.user.created_at }}</el-descriptions-item>
          </el-descriptions>
        </div>
      </el-col>

      <el-col :span="16">
        <div class="table-wrapper" style="margin-bottom:16px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
            <h3 style="font-size:16px">等级晋升进度</h3>
            <el-tag v-if="detail.upgrade_info?.can_upgrade" type="success" effect="dark">
              <el-icon style="vertical-align:-2px"><Top /></el-icon> 可升级！
            </el-tag>
          </div>
          <div v-if="detail.upgrade_info?.progress">
            <el-alert
              v-if="detail.upgrade_info.eligible_level"
              :title="`已达成 ${detail.upgrade_info.eligible_level.name} 条件，升级奖励 ${formatMoney(detail.upgrade_info.eligible_level.reward_bonus)} 元`"
              type="success"
              :closable="false"
              show-icon
              style="margin-bottom:16px"
            />
            <div v-if="detail.upgrade_info.progress.target_level">
              <h4 style="font-size:14px;margin-bottom:12px">
                下一级：<span style="color:#e6a23c">{{ detail.upgrade_info.progress.target_level.name }}</span>
                <span style="color:#909399;font-weight:normal;font-size:12px"> (等级{{ detail.upgrade_info.progress.target_level.level }})</span>
              </h4>
              <div class="progress-block">
                <div class="progress-label">
                  <span>业绩要求</span>
                  <span>{{ formatMoney(detail.upgrade_info.progress.achievement_current) }} / {{ formatMoney(detail.upgrade_info.progress.achievement_target) }} 元</span>
                </div>
                <el-progress
                  :percentage="detail.upgrade_info.progress.achievement_progress"
                  :stroke-width="14"
                  :text-inside="true"
                />
              </div>
              <div class="progress-block">
                <div class="progress-label">
                  <span>邀请人数要求</span>
                  <span>{{ detail.upgrade_info.progress.invite_current }} / {{ detail.upgrade_info.progress.invite_target }} 人</span>
                </div>
                <el-progress
                  :percentage="detail.upgrade_info.progress.invite_progress"
                  :stroke-width="14"
                  :text-inside="true"
                  :color="detail.upgrade_info.progress.invite_progress >= 100 ? '#67c23a' : '#409eff'"
                />
              </div>
            </div>
            <div v-else>
              <el-result icon="success" title="已达最高等级" sub-title="继续保持！" />
            </div>
          </div>
        </div>

        <div class="table-wrapper">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
            <h3 style="font-size:16px">升级记录 <el-badge :value="detail.upgrade_count || 0" class="ml-2" /></h3>
            <el-button size="small" type="primary" @click="showUpgradeHistory">查看全部</el-button>
          </div>
          <el-timeline v-if="detail.recent_upgrades?.length">
            <el-timeline-item
              v-for="(r, i) in detail.recent_upgrades"
              :key="r.id"
              :type="getTimelineType(r)"
              :timestamp="r.created_at"
              placement="top"
            >
              <el-card shadow="never" style="border:1px solid #ebeef5">
                <div style="display:flex;justify-content:space-between;align-items:center">
                  <div>
                    <span style="font-weight:600">
                      <s v-if="r.old_level" style="color:#909399;margin-right:8px">{{ r.old_level }}</s>
                      <el-icon style="color:#67c23a"><Right /></el-icon>
                      <span style="margin-left:8px;color:#67c23a">{{ r.new_level }}</span>
                    </span>
                    <el-tag size="small" style="margin-left:12px">{{ r.type_label }}</el-tag>
                  </div>
                  <div v-if="r.reward_bonus > 0" style="color:#f56c6c;font-weight:600">
                    +{{ formatMoney(r.reward_bonus) }} 元
                  </div>
                </div>
              </el-card>
            </el-timeline-item>
          </el-timeline>
          <div v-else class="empty-state">
            <el-icon><Document /></el-icon>
            <p>暂无升级记录</p>
          </div>
        </div>
      </el-col>
    </el-row>

    <el-row :gutter="16">
      <el-col :span="12">
        <div class="table-wrapper">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
            <h3 style="font-size:16px">上级邀请链路（祖先）</h3>
            <el-button size="small" type="primary" link @click="goToChain">查看完整链路图</el-button>
          </div>
          <div v-if="lineage.length">
            <div
              v-for="(item, i) in lineage"
              :key="item.user_id"
              style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px dashed #ebeef5"
            >
              <div style="display:flex;flex-direction:column;align-items:center;min-width:50px">
                <el-tag size="small" type="warning" effect="dark">L{{ item.depth }}</el-tag>
                <span style="font-size:10px;color:#c0c4cc;margin-top:2px">上{{ item.depth }}代</span>
              </div>
              <el-avatar :size="40" style="background:#909399">
                {{ (item.nickname || '?').charAt(0).toUpperCase() }}
              </el-avatar>
              <div style="flex:1">
                <div style="font-weight:600">
                  {{ item.nickname || item.username }}
                  <el-tag v-if="item.dealer_level" size="small" :type="getLevelTagType(item.dealer_level.level)" style="margin-left:6px">
                    {{ item.dealer_level.name }}
                  </el-tag>
                </div>
                <div style="font-size:12px;color:#909399;margin-top:2px">
                  业绩 {{ formatMoney(item.total_achievement) }} | 邀请 {{ item.total_invite_count }}人
                </div>
              </div>
            </div>
          </div>
          <div v-else class="empty-state">
            <el-icon><Connection /></el-icon>
            <p>该用户为顶级用户，无上级邀请链路</p>
          </div>
        </div>
      </el-col>

      <el-col :span="12">
        <div class="table-wrapper">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
            <h3 style="font-size:16px">邀请统计</h3>
          </div>
          <div class="stat-cards" style="grid-template-columns:repeat(2, 1fr);margin-bottom:16px">
            <div class="stat-card" style="padding:14px">
              <div class="stat-label">直接邀请</div>
              <div class="stat-value" style="font-size:22px">
                {{ inviteStats?.chain_stats?.direct_count || 0 }}
                <span class="unit" style="font-size:12px">人</span>
              </div>
            </div>
            <div class="stat-card" style="padding:14px">
              <div class="stat-label">间接邀请</div>
              <div class="stat-value" style="font-size:22px">
                {{ inviteStats?.chain_stats?.indirect_count || 0 }}
                <span class="unit" style="font-size:12px">人</span>
              </div>
            </div>
            <div class="stat-card" style="padding:14px">
              <div class="stat-label">累计佣金</div>
              <div class="stat-value" style="font-size:22px;color:#67c23a">
                {{ formatMoney(inviteStats?.chain_stats?.total_commission || 0) }}
                <span class="unit" style="font-size:12px;color:#909399">元</span>
              </div>
            </div>
            <div class="stat-card" style="padding:14px">
              <div class="stat-label">团队总业绩</div>
              <div class="stat-value" style="font-size:22px;color:#e6a23c">
                {{ formatMoney(inviteStats?.total_downline_achievement || 0) }}
                <span class="unit" style="font-size:12px;color:#909399">元</span>
              </div>
            </div>
          </div>
          <h4 style="font-size:14px;margin-bottom:10px">按深度分布</h4>
          <div ref="depthChartRef" style="width:100%;height:180px"></div>
          <div v-if="inviteStats?.recent_invitees?.length" style="margin-top:16px">
            <h4 style="font-size:14px;margin-bottom:10px">最近邀请</h4>
            <div
              v-for="u in inviteStats.recent_invitees.slice(0, 5)"
              :key="u.id"
              style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f5f7fa"
            >
              <el-avatar :size="32">{{ (u.nickname || u.username).charAt(0) }}</el-avatar>
              <div style="flex:1;font-size:13px">
                <span style="font-weight:500">{{ u.nickname || u.username }}</span>
                <span v-if="u.dealer_level" style="color:#67c23a;margin-left:6px">{{ u.dealer_level }}</span>
              </div>
              <span style="color:#909399;font-size:12px">{{ u.created_at?.slice(0, 10) }}</span>
            </div>
          </div>
        </div>
      </el-col>
    </el-row>

    <el-dialog v-model="historyDialogVisible" title="全部升级记录" width="700px">
      <el-table :data="upgradeHistory" size="small">
        <el-table-column label="升级内容">
          <template #default="{ row }">
            <s v-if="row.old_level" style="color:#909399">{{ row.old_level }}</s>
            <el-icon style="color:#67c23a;margin:0 4px"><Right /></el-icon>
            <span style="color:#67c23a;font-weight:600">{{ row.new_level }}</span>
          </template>
        </el-table-column>
        <el-table-column label="类型" width="100" align="center">
          <template #default="{ row }">
            <el-tag size="small">{{ row.type_label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="升级时业绩" width="120" align="right">
          <template #default="{ row }">{{ formatMoney(row.achievement_at_upgrade) }}</template>
        </el-table-column>
        <el-table-column label="升级时邀请" width="100" align="center">
          <template #default="{ row }">{{ row.invite_count_at_upgrade }}人</template>
        </el-table-column>
        <el-table-column label="奖励" width="110" align="right">
          <template #default="{ row }">
            <span v-if="row.reward_bonus > 0" style="color:#f56c6c;font-weight:600">
              +{{ formatMoney(row.reward_bonus) }}
            </span>
            <span v-else style="color:#c0c4cc">--</span>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="时间" width="160" align="center" />
      </el-table>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Top, Right, Document, Connection } from '@element-plus/icons-vue'
import { userApi, inviteChainApi, upgradeRecordApi } from '@/api'
import * as echarts from 'echarts'

const route = useRoute()
const router = useRouter()
const userId = route.params.id
const detail = ref(null)
const lineage = ref([])
const inviteStats = ref(null)
const upgradeHistory = ref([])
const historyDialogVisible = ref(false)
const depthChartRef = ref(null)
let depthChart = null

const formatMoney = (v) => Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const getLevelTagType = (lv) => {
  const types = ['', 'info', 'success', 'warning', 'danger']
  return types[Math.min(lv, 4)] || 'info'
}
const getTimelineType = (r) => {
  const types = { 1: 'primary', 2: 'success', 3: 'warning', 4: 'info' }
  return types[r.type] || 'primary'
}

const loadDetail = async () => {
  try {
    const res = await userApi.detail(userId)
    detail.value = res.data
    upgradeHistory.value = detail.value.recent_upgrades || []
  } catch {
    detail.value = {
      user: { id: userId, username: 'user_0001', nickname: '测试用户', phone: '138****1234', total_achievement: 268500, current_month_achievement: 42800, total_invite_count: 18, invite_depth: 2, status: 1, created_at: '2024-03-15 10:30:00' },
      dealer_level: { id: 3, name: '金牌经销商', level: 3 },
      inviter: { id: 2, username: 'user_0002', nickname: '邀请人A', dealer_level: { id: 4, name: '铂金经销商', level: 4 } },
      upgrade_info: {
        can_upgrade: true,
        eligible_level: { id: 4, name: '铂金经销商', level: 4, min_achievement: 300000, min_invite_count: 20, reward_bonus: 10000 },
        progress: {
          target_level: { id: 4, name: '铂金经销商', level: 4 },
          achievement_current: 268500, achievement_target: 300000, achievement_progress: 89.5,
          invite_current: 18, invite_target: 20, invite_progress: 90
        }
      },
      recent_upgrades: [
        { id: 1, old_level: '银牌经销商', new_level: '金牌经销商', type: 1, type_label: '自动升级', reward_bonus: 5000, created_at: '2024-10-15 14:20:00' },
        { id: 2, old_level: '普通经销商', new_level: '银牌经销商', type: 3, type_label: '邀请码升级', reward_bonus: 2000, created_at: '2024-06-20 09:15:00' }
      ],
      upgrade_count: 2
    }
    upgradeHistory.value = detail.value.recent_upgrades
  }
}

const loadLineage = async () => {
  try {
    const res = await inviteChainApi.getLineage(userId)
    lineage.value = res.data?.lineage || []
  } catch {
    lineage.value = [
      { depth: 1, user_id: 2, username: 'user_0002', nickname: '上级A', dealer_level: { id: 4, name: '铂金经销商', level: 4 }, total_achievement: 856000, total_invite_count: 86 },
      { depth: 2, user_id: 3, username: 'user_0003', nickname: '上级B', dealer_level: { id: 4, name: '铂金经销商', level: 4 }, total_achievement: 1280000, total_invite_count: 152 },
      { depth: 3, user_id: 4, username: 'user_0004', nickname: '创始人', dealer_level: { id: 4, name: '铂金经销商', level: 4 }, total_achievement: 3650000, total_invite_count: 520 }
    ]
  }
}

const loadInviteStats = async () => {
  try {
    const res = await inviteChainApi.getStats(userId)
    inviteStats.value = res.data
  } catch {
    inviteStats.value = {
      chain_stats: {
        total: 48, direct_count: 18, indirect_count: 30,
        total_commission: 42850, total_reward: 8600, rewarded_count: 18,
        depth_stats: { 1: 18, 2: 20, 3: 10 }
      },
      total_downline_achievement: 2685000,
      recent_invitees: [
        { id: 101, nickname: '小王', dealer_level: '银牌经销商', created_at: '2024-11-20' },
        { id: 102, nickname: '小李', dealer_level: '普通经销商', created_at: '2024-11-18' },
        { id: 103, nickname: '小张', dealer_level: '普通经销商', created_at: '2024-11-10' }
      ]
    }
  }
  await nextTick()
  renderDepthChart()
}

const renderDepthChart = () => {
  if (!depthChartRef.value) return
  if (!depthChart) depthChart = echarts.init(depthChartRef.value)
  const stats = inviteStats.value?.chain_stats?.depth_stats || {}
  const depths = Object.keys(stats).map(Number).sort((a, b) => a - b)
  depthChart.setOption({
    grid: { left: 30, right: 10, top: 10, bottom: 24 },
    xAxis: {
      type: 'category',
      data: depths.map(d => `L${d}层`),
      axisLine: { lineStyle: { color: '#dcdfe6' } }
    },
    yAxis: { type: 'value', splitLine: { lineStyle: { color: '#f0f0f0' } } },
    series: [{
      type: 'bar',
      data: depths.map(d => stats[d]),
      itemStyle: { color: '#409eff', borderRadius: [4, 4, 0, 0] },
      barWidth: 30,
      label: { show: true, position: 'top', formatter: '{c}人' }
    }]
  })
}

const showUpgradeHistory = async () => {
  try {
    const res = await upgradeRecordApi.userHistory(userId)
    upgradeHistory.value = res.data?.history || []
  } catch {
    upgradeHistory.value = detail.value?.recent_upgrades || []
  }
  historyDialogVisible.value = true
}

const goToChain = () => {
  router.push({ path: '/invite-chains', query: { user_id: userId } })
}

onMounted(() => {
  loadDetail()
  loadLineage()
  loadInviteStats()
})
</script>
