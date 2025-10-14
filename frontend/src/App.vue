<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import ReviewPanel from './components/ReviewPanel.vue'

const repo = ref('')
const prs = ref([])
const selectedPR = ref(null)
const loading = ref(false)
const error = ref(null)
const userRepos = ref([])
const loadingRepos = ref(false)
const searchQuery = ref('')
const showDropdown = ref(false)

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
    error.value = 'Please select a repository'
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

const filteredRepos = computed(() => {
  if (!searchQuery.value) return userRepos.value
  return userRepos.value.filter(r =>
    r.toLowerCase().includes(searchQuery.value.toLowerCase())
  )
})

const selectRepo = (repoName) => {
  repo.value = repoName
  searchQuery.value = repoName
  showDropdown.value = false
}

const handleInputFocus = () => {
  showDropdown.value = true
}

const handleInputBlur = () => {
  // Delay to allow click on dropdown item
  setTimeout(() => {
    showDropdown.value = false
  }, 200)
}

const handleSearchInput = (event) => {
  searchQuery.value = event.target.value
  repo.value = event.target.value
  showDropdown.value = true
}
</script>

<template>
  <div class="app-container">
    <header class="app-header">
      <div class="header-content">
        <div class="logo">
          <h1 class="app-title">Quode</h1>
          <p class="app-slogan">Review Smarter, Ship Faster</p>
        </div>
        <div class="header-actions">
          <div class="autocomplete">
            <input
              v-model="searchQuery"
              type="text"
              class="input"
              :placeholder="loadingRepos ? 'Loading repositories...' : 'Search repositories...'"
              :disabled="loadingRepos"
              @input="handleSearchInput"
              @focus="handleInputFocus"
              @blur="handleInputBlur"
            />
            <div v-if="showDropdown && filteredRepos.length > 0" class="dropdown">
              <div
                v-for="repoName in filteredRepos"
                :key="repoName"
                class="dropdown-item"
                @click="selectRepo(repoName)"
              >
                {{ repoName }}
              </div>
            </div>
            <div v-else-if="showDropdown && searchQuery && filteredRepos.length === 0" class="dropdown">
              <div class="dropdown-item-empty">No repositories found</div>
            </div>
          </div>
          <button class="btn" @click="fetchPRs" :disabled="loading || !repo">
            {{ loading ? 'Loading...' : 'Begin' }}
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
          <div class="welcome-header">
            <h2 class="empty-title">Welcome to Quode</h2>
            <p class="empty-text">AI-powered code review that helps you ship quality code faster</p>
          </div>
          <div class="features">
            <div class="feature-card">
              <div class="feature-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M9 11l3 3L22 4"></path>
                  <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                </svg>
              </div>
              <h3 class="feature-title">Deep Code Analysis</h3>
              <p class="feature-text">Comprehensive quality checks, best practices, and architectural insights powered by AI</p>
            </div>
            <div class="feature-card">
              <div class="feature-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                  <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
              </div>
              <h3 class="feature-title">Security First</h3>
              <p class="feature-text">Identify vulnerabilities, security risks, and potential exploits before they reach production</p>
            </div>
            <div class="feature-card">
              <div class="feature-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                </svg>
              </div>
              <h3 class="feature-title">Performance Insights</h3>
              <p class="feature-text">Smart optimization suggestions and efficiency improvements for faster, better code</p>
            </div>
          </div>
          <div class="getting-started">
            <p class="getting-started-text">Get started by selecting a repository from the dropdown above</p>
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