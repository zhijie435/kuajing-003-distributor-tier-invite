<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h2>数据概览</h2>
        <div class="subtitle">经销商等级、邀请码流转、升级记录实时数据</div>
      </div>
      <el-button type="primary" :icon="Refresh" @click="loadAll">刷新数据</el-button>
    </div>

    <div class="stat-cards">
      <div class="stat-card">
        <div class="stat-label"><el-icon><User /></el-icon>总用户数</div>
        <div class="stat-value">{{ userStats?.total_users || 0 }}</div>
        <div class="stat-footer">
          活跃 {{ userStats?.active_users || 0 }} 人
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><el-icon><Crown /></el-icon>有等级用户</div>
        <div class="stat-value">{{ userStats?.users_with_dealer_level || 0 }}</div>
        <div class="stat-footer">
          占比 {{ dealerLevelPercent }}%
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><el-icon><Wallet /></el-icon>总业绩</div>
        <div class="stat-value">
          {{ formatMoney(userStats?.total_achievement || 0) }}
          <span class="unit">元</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><el-icon><Share /></el-icon>总邀请数</div>
        <div class="stat-value">{{ userStats?.total_invite_count || 0 }}<span class="unit">人</span></div>
        <div class="stat-footer">
          有邀请人 {{ userStats?.users_with_inviter || 0 }} 人
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><el-icon><Key /></el-icon>邀请码总量</div>
        <div class="stat-value">{{ inviteCodeStats?.total || 0 }}</div>
        <div class="stat-footer">
          使用率 {{ inviteCodeStats?.usage_rate || 0 }}%
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><el-icon><TrendCharts /></el-icon>升级总数</div>
        <div class="stat-value">{{ upgradeStats?.overall?.total_upgrades || 0 }}</div>
        <div class="stat-footer">
          累计奖励 {{ formatMoney(upgradeStats?.overall?.total_bonus || 0) }} 元
        </div>
      </div>
    </div>

    <el-row :gutter="16" style="margin-bottom:16px">
      <el-col :span="12">
        <div class="table-wrapper" style="height:360px">
          <h3 style="margin-bottom:16px;font-size:16px">等级分布</h3>
          <div ref="levelChartRef" style="width:100%;height:300px"></div>
        </div>
      </el-col>
      <el-col :span="12">
        <div class="table-wrapper" style="height:360px">
          <h3 style="margin-bottom:16px;font-size:16px">升级类型分布</h3>
          <div ref="upgradeTypeChartRef" style="width:100%;height:300px"></div>
        </div>
      </el-col>
    </el-row>

    <el-row :gutter="16">
      <el-col :span="12">
        <div class="table-wrapper">
          <h3 style="margin-bottom:16px;font-size:16px">邀请码使用情况</h3>
          <el-row :gutter="12" style="margin-bottom:20px">
            <el-col :span="8">
              <el-statistic title="有效邀请码" :value="inviteCodeStats?.active || 0" />
            </el-col>
            <el-col :span="8">
              <el-statistic title="已使用完" :value="inviteCodeStats?.used_up || 0" />
            </el-col>
            <el-col :span="8">
              <el-statistic title="已过期" :value="inviteCodeStats?.expired || 0" />
            </el-col>
          </el-row>
          <el-progress
            type="dashboard"
            :percentage="inviteCodeStats?.usage_rate || 0"
            :color="progressColor"
          />
        </div>
      </el-col>
      <el-col :span="12">
        <div class="table-wrapper">
          <h3 style="margin-bottom:16px;font-size:16px">待处理升级奖励</h3>
          <el-alert
            title="有奖励待发放"
            :description="`待处理 ${upgradeStats?.overall?.pending_count || 0} 条，合计 ${formatMoney(upgradeStats?.pending_rewards_total || 0)} 元`"
            type="warning"
            :closable="false"
            show-icon
            style="margin-bottom:16px"
          />
          <el-table :data="upgradeStats?.by_level || []" size="small">
            <el-table-column prop="level_name" label="等级" />
            <el-table-column prop="upgrade_count" label="升级人数" width="120" align="center" />
            <el-table-column label="累计奖励(元)" width="160" align="center">
              <template #default="{ row }">
                {{ formatMoney(row.total_bonus) }}
              </template>
            </el-table-column>
          </el-table>
        </div>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch, nextTick } from 'vue'
import { Refresh, User, Crown, Wallet, Share, Key, TrendCharts } from '@element-plus/icons-vue'
import { dashboardApi } from '@/api'
import * as echarts from 'echarts'

const userStats = ref({})
const inviteCodeStats = ref({})
const upgradeStats = ref({})
const levelStats = ref({})
const levelChartRef = ref(null)
const upgradeTypeChartRef = ref(null)
let levelChart = null
let upgradeTypeChart = null

const dealerLevelPercent = computed(() => {
  if (!userStats.value?.total_users) return 0
  return Math.round((userStats.value.users_with_dealer_level / userStats.value.total_users) * 100)
})

const progressColor = computed(() => {
  const rate = inviteCodeStats.value?.usage_rate || 0
  if (rate >= 90) return '#f56c6c'
  if (rate >= 70) return '#e6a23c'
  return '#67c23a'
})

const formatMoney = (val) => {
  return Number(val || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const loadAll = async () => {
  try {
    const [userRes, levelRes, codeRes, upgradeRes] = await Promise.all([
      dashboardApi.getUserStats(),
      dashboardApi.getDealerLevelStats(),
      dashboardApi.getInviteCodeStats(),
      dashboardApi.getUpgradeStats()
    ])
    userStats.value = userRes.data
    levelStats.value = levelRes.data
    inviteCodeStats.value = codeRes.data
    upgradeStats.value = upgradeRes.data
    await nextTick()
    renderCharts()
  } catch (e) {
    loadMockData()
    await nextTick()
    renderCharts()
  }
}

const loadMockData = () => {
  userStats.value = {
    total_users: 1286,
    active_users: 1024,
    users_with_dealer_level: 856,
    users_with_inviter: 1102,
    users_without_inviter: 184,
    total_achievement: 3685200.50,
    total_invite_count: 1102,
    dealer_level_distribution: [
      { level_id: 1, level_name: '普通经销商', level_weight: 1, user_count: 412 },
      { level_id: 2, level_name: '银牌经销商', level_weight: 2, user_count: 258 },
      { level_id: 3, level_name: '金牌经销商', level_weight: 3, user_count: 145 },
      { level_id: 4, level_name: '铂金经销商', level_weight: 4, user_count: 41 }
    ]
  }
  inviteCodeStats.value = {
    total: 3520,
    active: 1860,
    used_up: 1320,
    expired: 300,
    disabled: 40,
    total_used_count: 4820,
    total_max_uses: 7040,
    total_reward_amount: 268500,
    total_new_user_bonus: 96400,
    usage_rate: 68.5
  }
  upgradeStats.value = {
    overall: {
      total_upgrades: 428,
      total_bonus: 268800,
      rewarded_count: 396,
      pending_count: 32,
      auto_count: 312,
      manual_count: 25,
      code_count: 78,
      admin_count: 13
    },
    pending_rewards_total: 52800,
    by_level: [
      { level_id: 2, level_name: '银牌经销商', upgrade_count: 258, total_bonus: 129000 },
      { level_id: 3, level_name: '金牌经销商', upgrade_count: 145, total_bonus: 217500 },
      { level_id: 4, level_name: '铂金经销商', upgrade_count: 41, total_bonus: 410000 }
    ]
  }
}

const renderCharts = () => {
  if (levelChartRef.value) {
    if (!levelChart) levelChart = echarts.init(levelChartRef.value)
    const dist = userStats.value.dealer_level_distribution || []
    levelChart.setOption({
      tooltip: { trigger: 'item', formatter: '{b}: {c}人 ({d}%)' },
      legend: { bottom: '0%' },
      series: [{
        type: 'pie',
        radius: ['40%', '65%'],
        avoidLabelOverlap: false,
        itemStyle: { borderRadius: 6, borderColor: '#fff', borderWidth: 2 },
        label: { show: false },
        emphasis: {
          label: { show: true, fontSize: 14, fontWeight: 'bold' }
        },
        data: dist.map((d, i) => ({
          value: d.user_count,
          name: d.level_name,
          itemStyle: { color: ['#409eff', '#67c23a', '#e6a23c', '#f56c6c', '#909399'][i % 5] }
        }))
      }]
    })
  }

  if (upgradeTypeChartRef.value) {
    if (!upgradeTypeChart) upgradeTypeChart = echarts.init(upgradeTypeChartRef.value)
    const o = upgradeStats.value.overall || {}
    upgradeTypeChart.setOption({
      tooltip: { trigger: 'item', formatter: '{b}: {c}次 ({d}%)' },
      legend: { bottom: '0%' },
      series: [{
        type: 'pie',
        radius: '60%',
        itemStyle: { borderRadius: 6 },
        data: [
          { value: o.auto_count || 0, name: '自动升级', itemStyle: { color: '#409eff' } },
          { value: o.code_count || 0, name: '邀请码升级', itemStyle: { color: '#67c23a' } },
          { value: o.manual_count || 0, name: '手动升级', itemStyle: { color: '#e6a23c' } },
          { value: o.admin_count || 0, name: '后台调整', itemStyle: { color: '#909399' } }
        ]
      }]
    })
  }
}

watch([levelStats, upgradeStats, inviteCodeStats], () => {
  nextTick(renderCharts)
}, { deep: true })

onMounted(loadAll)
</script>
