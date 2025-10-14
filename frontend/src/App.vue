<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import ReviewPanel from './components/ReviewPanel.vue'

const repo = ref('')
const prs = ref([])
const selectedPR = ref(null)
const loading = ref(false)
const error = ref(null)
const userRepos = ref([])
const loadingRepos = ref(false)

// Fetch user's GitHub repositories
const fetchUserRepos = async () => {
  loadingRepos.value = true
  try {
    const response = await axios.get('/api/repos')
    userRepos.value = response.data.repos || []
  } catch (err) {
    console.error('Failed to fetch repos:', err)
    // Silently fail - user can still type manually
  } finally {
    loadingRepos.value = false
  }
}

// Load repos on component mount
onMounted(() => {
  fetchUserRepos()
})

const fetchPRs = async () => {
  if (!repo.value) {
    error.value = 'Please enter a repository (e.g., owner/repo)'
    return
  }

  loading.value = true
  error.value = null
  prs.value = []
  selectedPR.value = null

  try {
    const response = await axios.get(`/api/prs?repo=${encodeURIComponent(repo.value)}`)
    prs.value = response.data.prs || []
    
    if (prs.value.length === 0) {
      error.value = 'No pull requests found'
    }
  } catch (err) {
    error.value = err.response?.data?.error || 'Failed to fetch PRs'
  } finally {
    loading.value = false
  }
}

const selectPR = (pr) => {
  selectedPR.value = pr
}

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString('en-US', { 
    month: 'short', 
    day: 'numeric'
  })
}
</script>

<template>
  <div class="app-container">
    <header class="app-header">
      <div class="header-content">
        <h1 class="app-title">AI Code Review</h1>
        <div class="header-actions">
          <input
            v-model="repo"
            type="text"
            class="input"
            list="repo-suggestions"
            placeholder="owner/repository"
            @keyup.enter="fetchPRs"
          />
          <datalist id="repo-suggestions">
            <option v-for="repoName in userRepos" :key="repoName" :value="repoName">
              {{ repoName }}
            </option>
          </datalist>
          <button class="btn" @click="fetchPRs" :disabled="loading">
            {{ loading ? 'Loading...' : 'Load PRs' }}
          </button>
        </div>
      </div>
    </header>

    <div v-if="error" class="error" style="margin: 1rem 2rem;">
      {{ error }}
    </div>

    <main class="app-main">
      <aside v-if="prs.length > 0" class="sidebar">
        <div class="sidebar-header">
          <span class="sidebar-title">Pull Requests</span>
          <span class="count-badge">{{ prs.length }}</span>
        </div>
        <ul class="pr-list">
          <li
            v-for="pr in prs"
            :key="pr.number"
            class="pr-item"
            :class="{ active: selectedPR?.number === pr.number }"
            @click="selectPR(pr)"
          >
            <div class="pr-number">#{{ pr.number }}</div>
            <div class="pr-title">{{ pr.title }}</div>
            <div class="pr-meta">
              {{ pr.author.login }} • {{ formatDate(pr.updatedAt) }}
            </div>
          </li>
        </ul>
      </aside>

      <div class="content-panel">
        <div v-if="loading" class="loading">
          <div class="spinner"></div>
          <p>Fetching pull requests...</p>
        </div>

        <div v-else-if="!prs.length && !error" class="empty-state">
          <h2 class="empty-title">Welcome to AI Code Review</h2>
          <p class="empty-text">Enter a GitHub repository above to get started</p>
          <div class="features">
            <div class="feature-card">
              <h3 class="feature-title">Deep Analysis</h3>
              <p class="feature-text">Comprehensive code quality checks and best practices</p>
            </div>
            <div class="feature-card">
              <h3 class="feature-title">Security Review</h3>
              <p class="feature-text">Identify vulnerabilities and security concerns</p>
            </div>
            <div class="feature-card">
              <h3 class="feature-title">Performance</h3>
              <p class="feature-text">Optimization suggestions and improvements</p>
            </div>
          </div>
        </div>

        <ReviewPanel v-else-if="selectedPR" :pr="selectedPR" :repo="repo" />

        <div v-else-if="prs.length" class="empty-state">
          <h2 class="empty-title">Select a Pull Request</h2>
          <p class="empty-text">Choose a PR from the sidebar to start reviewing</p>
        </div>
      </div>
    </main>
  </div>
</template>