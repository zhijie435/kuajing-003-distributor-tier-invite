import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/',
    redirect: '/dashboard'
  },
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: () => import('@/views/Dashboard.vue'),
    meta: { title: '数据概览' }
  },
  {
    path: '/users',
    name: 'Users',
    component: () => import('@/views/Users.vue'),
    meta: { title: '用户管理' }
  },
  {
    path: '/users/:id',
    name: 'UserDetail',
    component: () => import('@/views/UserDetail.vue'),
    meta: { title: '用户详情' }
  },
  {
    path: '/dealer-levels',
    name: 'DealerLevels',
    component: () => import('@/views/DealerLevels.vue'),
    meta: { title: '等级管理' }
  },
  {
    path: '/invite-codes',
    name: 'InviteCodes',
    component: () => import('@/views/InviteCodes.vue'),
    meta: { title: '邀请码管理' }
  },
  {
    path: '/invite-chains',
    name: 'InviteChains',
    component: () => import('@/views/InviteChains.vue'),
    meta: { title: '邀请链路' }
  },
  {
    path: '/upgrade-records',
    name: 'UpgradeRecords',
    component: () => import('@/views/UpgradeRecords.vue'),
    meta: { title: '升级记录' }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

export default router
