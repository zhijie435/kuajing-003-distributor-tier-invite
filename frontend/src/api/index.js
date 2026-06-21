import request from '@/utils/request'

export const dashboardApi = {
  getUserStats() {
    return request.get('/users/stats')
  },
  getDealerLevelStats() {
    return request.get('/dealer-levels/stats')
  },
  getInviteCodeStats(params) {
    return request.get('/invite-codes/stats', { params })
  },
  getUpgradeStats(params) {
    return request.get('/upgrade-records/stats', { params })
  }
}

export const userApi = {
  list(params) {
    return request.get('/users', { params })
  },
  detail(id) {
    return request.get(`/users/${id}`)
  },
  create(data) {
    return request.post('/users', data)
  },
  update(id, data) {
    return request.put(`/users/${id}`, data)
  },
  addAchievement(id, data) {
    return request.patch(`/users/${id}/add-achievement`, data)
  },
  getInvitees(id, params) {
    return request.get(`/users/${id}/invitees`, { params })
  }
}

export const dealerLevelApi = {
  list(params) {
    return request.get('/dealer-levels', { params })
  },
  all() {
    return request.get('/dealer-levels/all')
  },
  stats() {
    return request.get('/dealer-levels/stats')
  },
  detail(id) {
    return request.get(`/dealer-levels/${id}`)
  },
  create(data) {
    return request.post('/dealer-levels', data)
  },
  update(id, data) {
    return request.put(`/dealer-levels/${id}`, data)
  },
  remove(id) {
    return request.delete(`/dealer-levels/${id}`)
  },
  toggle(id) {
    return request.patch(`/dealer-levels/${id}/toggle`)
  }
}

export const inviteCodeApi = {
  list(params) {
    return request.get('/invite-codes', { params })
  },
  detail(id) {
    return request.get(`/invite-codes/${id}`)
  },
  findByCode(code) {
    return request.get(`/invite-codes/code/${code}`)
  },
  check(params) {
    return request.get('/invite-codes/check', { params })
  },
  stats(params) {
    return request.get('/invite-codes/stats', { params })
  },
  create(data) {
    return request.post('/invite-codes', data)
  },
  batchCreate(data) {
    return request.post('/invite-codes/batch', data)
  },
  update(id, data) {
    return request.put(`/invite-codes/${id}`, data)
  },
  remove(id) {
    return request.delete(`/invite-codes/${id}`)
  },
  toggle(id) {
    return request.patch(`/invite-codes/${id}/toggle`)
  }
}

export const inviteChainApi = {
  list(params) {
    return request.get('/invite-chains', { params })
  },
  detail(id) {
    return request.get(`/invite-chains/${id}`)
  },
  useCode(data) {
    return request.post('/invite-chains/use-code', data)
  },
  createDirect(data) {
    return request.post('/invite-chains/create', data)
  },
  getStats(userId) {
    return request.get(`/invite-chains/user/${userId}/stats`)
  },
  getLineage(userId) {
    return request.get(`/invite-chains/user/${userId}/lineage`)
  },
  getTree(userId, params) {
    return request.get(`/invite-chains/user/${userId}/tree`, { params })
  },
  markRewarded(id) {
    return request.patch(`/invite-chains/${id}/reward`)
  },
  batchMarkRewarded(data) {
    return request.patch('/invite-chains/batch/reward', data)
  }
}

export const upgradeRecordApi = {
  list(params) {
    return request.get('/upgrade-records', { params })
  },
  detail(id) {
    return request.get(`/upgrade-records/${id}`)
  },
  userHistory(userId, params) {
    return request.get(`/upgrade-records/user/${userId}/history`, { params })
  },
  stats(params) {
    return request.get('/upgrade-records/stats', { params })
  },
  manualUpgrade(data) {
    return request.post('/upgrade-records/manual-upgrade', data)
  },
  checkAutoUpgrade(data) {
    return request.post('/upgrade-records/check-auto-upgrade', data)
  },
  markRewarded(id) {
    return request.patch(`/upgrade-records/${id}/reward`)
  },
  batchMarkRewarded(data) {
    return request.patch('/upgrade-records/batch/reward', data)
  },
  rewardAllPending(data) {
    return request.patch('/upgrade-records/reward-all-pending', data)
  }
}
