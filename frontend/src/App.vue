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
const showHistoryModal = ref(false)
const savedReviews = ref([])
const historyFilter = ref('')
const showSettingsModal = ref(false)
const favoriteRepos = ref([])
const settingsSearch = ref('')
const settingsTab = ref('repos')
const customGuidelines = ref('')
const guidelinesFileName = ref('')

// Fetch user's GitHub repositories
const fetchUserRepos = async (refresh = false) => {
  loadingRepos.value = true
  try {
    const url = refresh ? '/api/repos?refresh=true' : '/api/repos'
    const response = await axios.get(url)
    userRepos.value = response.data.repos || []
  } catch (err) {
    console.error('Failed to fetch repos:', err)
    // Silently fail - user can still type manually
  } finally {
    loadingRepos.value = false
  }
}

// Refresh repos from GitHub API
const refreshRepos = () => {
  fetchUserRepos(true)
}

// Load favorite repos from localStorage
const loadFavoriteRepos = () => {
  const saved = localStorage.getItem('quode_favorite_repos')
  if (saved) {
    favoriteRepos.value = JSON.parse(saved)
  }
}

// Load custom guidelines from localStorage
const loadCustomGuidelines = () => {
  const saved = localStorage.getItem('quode_custom_guidelines')
  if (saved) {
    const data = JSON.parse(saved)
    customGuidelines.value = data.content || ''
    guidelinesFileName.value = data.fileName || ''
  }
}

// Save favorite repos to localStorage
const saveFavoriteRepos = () => {
  localStorage.setItem('quode_favorite_repos', JSON.stringify(favoriteRepos.value))
}

// Save custom guidelines to localStorage
const saveCustomGuidelines = () => {
  const data = {
    content: customGuidelines.value,
    fileName: guidelinesFileName.value,
    updatedAt: new Date().toISOString()
  }
  localStorage.setItem('quode_custom_guidelines', JSON.stringify(data))
}

// Handle file upload
const handleGuidelinesFileUpload = (event) => {
  const file = event.target.files[0]
  if (!file) return

  const reader = new FileReader()
  reader.onload = (e) => {
    customGuidelines.value = e.target.result
    guidelinesFileName.value = file.name
    saveCustomGuidelines()
  }
  reader.readAsText(file)
}

// Clear guidelines
const clearGuidelines = () => {
  if (confirm('Are you sure you want to clear your custom guidelines?')) {
    customGuidelines.value = ''
    guidelinesFileName.value = ''
    localStorage.removeItem('quode_custom_guidelines')
  }
}

// Load repos on component mount
onMounted(() => {
  fetchUserRepos()
  loadFavoriteRepos()
  loadCustomGuidelines()
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

const prSearchQuery = ref('')

const filteredPRs = computed(() => {
  if (!prSearchQuery.value) return prs.value
  const query = prSearchQuery.value.toLowerCase()
  return prs.value.filter(pr =>
    pr.title.toLowerCase().includes(query) ||
    pr.number.toString().includes(query) ||
    pr.author.login.toLowerCase().includes(query)
  )
})

const filteredRepos = computed(() => {
  // If favorites are set, only show favorite repos
  let reposToShow = favoriteRepos.value.length > 0 ? favoriteRepos.value : userRepos.value

  if (!searchQuery.value) return reposToShow
  return reposToShow.filter(r =>
    r.toLowerCase().includes(searchQuery.value.toLowerCase())
  )
})

const filteredAllRepos = computed(() => {
  if (!settingsSearch.value) return userRepos.value
  return userRepos.value.filter(r =>
    r.toLowerCase().includes(settingsSearch.value.toLowerCase())
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

// Review History Functions
const loadReviewHistory = () => {
  savedReviews.value = JSON.parse(localStorage.getItem('quode_reviews') || '[]')
  showHistoryModal.value = true
}

const closeHistoryModal = () => {
  showHistoryModal.value = false
  historyFilter.value = ''
}

const filteredHistory = computed(() => {
  if (!historyFilter.value) return savedReviews.value
  const query = historyFilter.value.toLowerCase()
  return savedReviews.value.filter(r =>
    r.repo.toLowerCase().includes(query) ||
    r.prTitle.toLowerCase().includes(query) ||
    r.prNumber.toString().includes(query)
  )
})

const loadSavedReview = (reviewData) => {
  repo.value = reviewData.repo
  searchQuery.value = reviewData.repo

  // Create PR object from saved data
  const prObject = {
    number: reviewData.prNumber,
    title: reviewData.prTitle,
    author: { login: reviewData.prAuthor },
    updatedAt: reviewData.savedAt
  }

  // Add to PRs list if not already there
  if (!prs.value.find(p => p.number === prObject.number)) {
    prs.value = [prObject, ...prs.value]
  }

  selectedPR.value = prObject
  closeHistoryModal()
}

const deleteReview = (reviewId) => {
  if (!confirm('Are you sure you want to delete this review?')) return

  const reviews = JSON.parse(localStorage.getItem('quode_reviews') || '[]')
  const filtered = reviews.filter(r => r.id !== reviewId)
  localStorage.setItem('quode_reviews', JSON.stringify(filtered))
  savedReviews.value = filtered
}

const formatDateTime = (dateString) => {
  return new Date(dateString).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const goHome = () => {
  repo.value = ''
  searchQuery.value = ''
  prs.value = []
  selectedPR.value = null
  error.value = null
}

// Settings Modal Functions
const openSettings = () => {
  showSettingsModal.value = true
}

const closeSettings = () => {
  showSettingsModal.value = false
  settingsSearch.value = ''
  settingsTab.value = 'repos'
}

const toggleFavoriteRepo = (repoName) => {
  const index = favoriteRepos.value.indexOf(repoName)
  if (index > -1) {
    favoriteRepos.value.splice(index, 1)
  } else {
    favoriteRepos.value.push(repoName)
  }
  saveFavoriteRepos()
}

const isFavorite = (repoName) => {
  return favoriteRepos.value.includes(repoName)
}

const clearAllFavorites = () => {
  if (confirm('Are you sure you want to clear all favorite repositories?')) {
    favoriteRepos.value = []
    saveFavoriteRepos()
  }
}
</script>

<template>
  <div class="app-container">
    <header class="app-header">
      <div class="header-content">
        <div class="logo logo-clickable" @click="goHome">
          <div class="title-container">
            <h1 class="app-title">Quode</h1>
            <span class="ai-badge">AI</span>
          </div>
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
        <div class="header-icons">
          <button class="icon-btn" @click="refreshRepos" title="Refresh repositories" :disabled="loadingRepos">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :class="{ 'spin': loadingRepos }">
              <polyline points="23 4 23 10 17 10"></polyline>
              <polyline points="1 20 1 14 7 14"></polyline>
              <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
            </svg>
          </button>
          <button class="icon-btn" @click="openSettings" title="Settings">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
          </button>
          <button class="icon-btn" @click="loadReviewHistory" title="History">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"></circle>
              <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
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
          <span class="count-badge">{{ filteredPRs.length }}</span>
        </div>
        <div class="sidebar-search">
          <input
            v-model="prSearchQuery"
            type="text"
            class="pr-search-input"
            placeholder="Search PRs..."
          />
          <svg v-if="!prSearchQuery" class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.35-4.35"></path>
          </svg>
          <button v-else class="clear-search" @click="prSearchQuery = ''">×</button>
        </div>
        <ul class="pr-list">
          <li
            v-for="pr in filteredPRs"
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
        <div v-if="filteredPRs.length === 0" class="pr-list-empty">
          <p>No PRs match your search</p>
        </div>
      </aside>

      <div class="content-panel">
        <div v-if="loading" class="loading">
          <div class="spinner"></div>
          <p>Fetching pull requests...</p>
        </div>

        <div v-else-if="!prs.length && !error" class="empty-state">
          <div class="welcome-header">
            <div class="welcome-title-container">
              <h2 class="empty-title">Welcome to Quode</h2>
              <span class="ai-badge-large">AI</span>
            </div>
            <p class="empty-text">Intelligent code review with AI-powered insights and actionable recommendations</p>
          </div>
          <div class="features">
            <div class="feature-card">
              <div class="feature-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                  <polyline points="7.5 4.21 12 6.81 16.5 4.21"></polyline>
                  <polyline points="7.5 19.79 7.5 14.6 3 12"></polyline>
                  <polyline points="21 12 16.5 14.6 16.5 19.79"></polyline>
                  <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                  <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
              </div>
              <h3 class="feature-title">Detailed Analysis</h3>
              <p class="feature-text">Comprehensive code review covering security issues, performance problems, and code quality improvements</p>
            </div>
            <div class="feature-card">
              <div class="feature-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                  <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
              </div>
              <h3 class="feature-title">Security Pattern Detection</h3>
              <p class="feature-text">Automatically detect dangerous patterns like eval(), innerHTML, and potential secrets in your code</p>
            </div>
            <div class="feature-card">
              <div class="feature-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                  <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
              </div>
              <h3 class="feature-title">AI-Powered Review</h3>
              <p class="feature-text">Comprehensive code quality analysis with actionable feedback on architecture, best practices, and improvements</p>
            </div>
          </div>
        </div>

        <ReviewPanel v-else-if="selectedPR" :pr="selectedPR" :repo="repo" :customGuidelines="customGuidelines" />

        <div v-else-if="prs.length" class="empty-state">
          <h2 class="empty-title">Select a Pull Request</h2>
          <p class="empty-text">Choose a PR from the sidebar to start reviewing</p>
        </div>
      </div>
    </main>

    <!-- Settings Modal -->
    <Teleport to="body">
      <div v-if="showSettingsModal" class="modal-overlay" @click="closeSettings">
        <div class="history-modal" @click.stop>
          <div class="history-header">
            <h2 class="history-title">Settings</h2>
            <button class="modal-close" @click="closeSettings">×</button>
          </div>

          <!-- Settings Tabs -->
          <div class="settings-tabs">
            <button
              class="settings-tab"
              :class="{ active: settingsTab === 'repos' }"
              @click="settingsTab = 'repos'"
            >
              Repositories
            </button>
            <button
              class="settings-tab"
              :class="{ active: settingsTab === 'guidelines' }"
              @click="settingsTab = 'guidelines'"
            >
              Review Guidelines
            </button>
          </div>

          <!-- Tab Content Container -->
          <div class="settings-tab-content">
            <!-- Repositories Tab -->
            <div v-if="settingsTab === 'repos'" class="settings-tab-panel">
              <div class="settings-info">
                <p class="settings-description">
                  Select your favorite repositories to display in the dropdown.
                  If no favorites are selected, all repositories will be shown.
                </p>
                <div class="settings-stats">
                  <span class="stat-item">
                    <strong>{{ favoriteRepos.length }}</strong> favorites selected
                  </span>
                  <span class="stat-separator">•</span>
                  <span class="stat-item">
                    <strong>{{ userRepos.length }}</strong> total repos
                  </span>
                </div>
              </div>

              <div class="history-search">
                <input
                  v-model="settingsSearch"
                  type="text"
                  class="input"
                  placeholder="Search repositories..."
                />
                <button
                  v-if="favoriteRepos.length > 0"
                  class="btn-clear-favorites"
                  @click="clearAllFavorites"
                >
                  Clear All Favorites
                </button>
              </div>

              <div class="history-body">
                <div v-if="filteredAllRepos.length === 0" class="history-empty">
                  <p>No repositories found.</p>
                </div>

                <div v-else class="settings-list">
                  <div
                    v-for="repoName in filteredAllRepos"
                    :key="repoName"
                    class="settings-item"
                    :class="{ 'is-favorite': isFavorite(repoName) }"
                    @click="toggleFavoriteRepo(repoName)"
                  >
                    <div class="settings-checkbox">
                      <input
                        type="checkbox"
                        :checked="isFavorite(repoName)"
                        @click.stop="toggleFavoriteRepo(repoName)"
                      />
                    </div>
                    <div class="settings-repo-name">{{ repoName }}</div>
                    <div v-if="isFavorite(repoName)" class="favorite-badge">
                      ★
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Guidelines Tab -->
            <div v-if="settingsTab === 'guidelines'" class="settings-tab-panel">
              <div class="settings-info">
                <p class="settings-description">
                  Add your company's code review guidelines to customize AI reviews. Upload a file or paste your guidelines below.
                  These will be included in every AI review.
                </p>
                <div v-if="guidelinesFileName" class="settings-stats">
                  <span class="stat-item">
                    <strong>Current file:</strong> {{ guidelinesFileName }}
                  </span>
                </div>
              </div>

              <div class="guidelines-controls">
                <label class="file-upload-btn">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="17 8 12 3 7 8"></polyline>
                    <line x1="12" y1="3" x2="12" y2="15"></line>
                  </svg>
                  Upload File
                  <input
                    type="file"
                    accept=".txt,.md,.doc,.docx"
                    @change="handleGuidelinesFileUpload"
                    style="display: none;"
                  />
                </label>
                <button
                  v-if="customGuidelines"
                  class="btn-clear-favorites"
                  @click="clearGuidelines"
                >
                  Clear Guidelines
                </button>
              </div>

              <div class="history-body">
                <textarea
                  v-model="customGuidelines"
                  class="guidelines-textarea"
                  placeholder="Paste your code review guidelines here...

Example:
- Always require JSDoc comments for public functions
- Ensure error handling for all async operations
- Follow our naming convention: camelCase for variables, PascalCase for classes
- All new features must include unit tests"
                  @blur="saveCustomGuidelines"
                ></textarea>

                <div v-if="customGuidelines" class="guidelines-preview">
                  <h4>Preview ({{ customGuidelines.length }} characters)</h4>
                  <div class="preview-content">{{ customGuidelines.substring(0, 200) }}{{ customGuidelines.length > 200 ? '...' : '' }}</div>
                </div>
              </div>
            </div>
          </div>

          <div class="history-footer">
            <button class="btn" @click="closeSettings">Done</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Review History Modal -->
    <Teleport to="body">
      <div v-if="showHistoryModal" class="modal-overlay" @click="closeHistoryModal">
        <div class="history-modal" @click.stop>
          <div class="history-header">
            <h2 class="history-title">Review History</h2>
            <button class="modal-close" @click="closeHistoryModal">×</button>
          </div>

          <div class="history-search">
            <input
              v-model="historyFilter"
              type="text"
              class="input"
              placeholder="Filter by repository, PR title, or number..."
            />
          </div>

          <div class="history-body">
            <div v-if="filteredHistory.length === 0" class="history-empty">
              <p v-if="savedReviews.length === 0">No saved reviews yet. Generate a review to see it here!</p>
              <p v-else>No reviews match your search.</p>
            </div>

            <div v-else class="history-list">
              <div
                v-for="review in filteredHistory"
                :key="review.id"
                class="history-item"
              >
                <div class="history-item-content">
                  <div class="history-item-header">
                    <span class="history-pr-number">#{{ review.prNumber }}</span>
                    <span class="history-repo">{{ review.repo }}</span>
                  </div>
                  <h3 class="history-pr-title">{{ review.prTitle }}</h3>
                  <div class="history-meta">
                    <span>{{ review.prAuthor }}</span>
                    <span>•</span>
                    <span>{{ formatDateTime(review.savedAt) }}</span>
                  </div>
                </div>
                <div class="history-item-actions">
                  <button class="btn-load" @click="loadSavedReview(review)">
                    Load
                  </button>
                  <button class="btn-delete" @click="deleteReview(review.id)">
                    Delete
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="history-footer">
            <button class="btn" @click="closeHistoryModal">Close</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>