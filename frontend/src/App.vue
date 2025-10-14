<script setup>
import { ref } from 'vue'
import axios from 'axios'
import ReviewPanel from './components/ReviewPanel.vue'

const repo = ref('')
const prs = ref([])
const selectedPR = ref(null)
const loading = ref(false)
const error = ref(null)

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
      <div class="header-top">
        <div class="logo">
          <div>
            <h1>AI Code Review</h1>
            <div class="badges">
              <span class="badge badge-pink">Gemini</span>
              <span class="badge badge-purple">GitHub</span>
            </div>
          </div>
        </div>
      </div>

      <div class="search-bar">
        <input
          v-model="repo"
          type="text"
          class="input"
          placeholder="Enter repository (e.g., facebook/react, microsoft/typescript)"
          @keyup.enter="fetchPRs"
        />
        <button class="btn" @click="fetchPRs" :disabled="loading">
          {{ loading ? 'Loading...' : 'Fetch PRs' }}
        </button>
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