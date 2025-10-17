<script setup>
import { ref, watch, computed } from 'vue'
import axios from 'axios'
import { marked } from 'marked'

const props = defineProps({
  pr: Object,
  repo: String,
  customGuidelines: String
})

const loading = ref(false)
const loadingStage = ref('Initialising')
const aiProgress = ref('')
const error = ref(null)
const review = ref(null)
const prDetails = ref(null)
const showModal = ref(false)
const activeTab = ref('actionable')
const modalActiveTab = ref('actionable')
const prInfoTab = ref('overview')
const showPRDetails = ref(false)
const loadingPRDetails = ref(false)
const complexityData = ref(null)

// Chat functionality
const chatMessages = ref([])
const chatInput = ref('')
const chatLoading = ref(false)

const sendChatMessage = async () => {
  if (!chatInput.value.trim() || !review.value) return

  const userMessage = {
    role: 'user',
    content: chatInput.value,
    timestamp: new Date().toISOString()
  }

  chatMessages.value.push(userMessage)
  const question = chatInput.value
  chatInput.value = ''
  chatLoading.value = true

  try {
    const response = await axios.post('/api/chat', {
      pr: prDetails.value,
      review: review.value.content,
      messages: chatMessages.value,
      question: question
    })

    const aiMessage = {
      role: 'assistant',
      content: response.data.response,
      timestamp: new Date().toISOString()
    }

    chatMessages.value.push(aiMessage)
  } catch (err) {
    const errorMessage = {
      role: 'error',
      content: 'Failed to get response: ' + (err.response?.data?.error || err.message),
      timestamp: new Date().toISOString()
    }
    chatMessages.value.push(errorMessage)
  } finally {
    chatLoading.value = false
  }
}

const clearChat = () => {
  if (confirm('Clear all chat messages?')) {
    chatMessages.value = []
  }
}

const openModal = () => {
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
}

const parseSections = computed(() => {
  if (!review.value?.content) return null

  const content = review.value.content
  const sections = {
    actionable: '',
    testScenarios: ''
  }

  // Split content by section markers
  const actionableMatch = content.match(/## SECTION: ACTIONABLE_ITEMS([\s\S]*?)(?=## SECTION:|$)/i)
  const testScenariosMatch = content.match(/## SECTION: TEST_SCENARIOS([\s\S]*?)(?=## SECTION:|$)/i)

  if (actionableMatch) sections.actionable = actionableMatch[1].trim()
  if (testScenariosMatch) sections.testScenarios = testScenariosMatch[1].trim()

  return sections
})

const actionableHtml = computed(() => {
  if (!parseSections.value?.actionable) return ''
  return marked.parse(parseSections.value.actionable)
})

const testScenariosHtml = computed(() => {
  if (!parseSections.value?.testScenarios) return ''
  return marked.parse(parseSections.value.testScenarios)
})

// Configure marked for better code review rendering
marked.setOptions({
  breaks: true,
  gfm: true
})

const reviewHtml = computed(() => {
  if (!review.value?.content) return '';
  return marked.parse(review.value.content);
})

// Save review to localStorage
const saveReview = () => {
  if (!review.value) return

  const savedReviews = JSON.parse(localStorage.getItem('quode_reviews') || '[]')

  // Remove any existing review for this PR (to avoid duplicates)
  const filtered = savedReviews.filter(r => !(r.repo === props.repo && r.prNumber === props.pr.number))

  const reviewData = {
    id: `${props.repo}-${props.pr.number}-${Date.now()}`,
    repo: props.repo,
    prNumber: props.pr.number,
    prTitle: props.pr.title,
    prAuthor: props.pr.author?.login || 'Unknown',
    review: review.value,
    prDetails: prDetails.value,
    savedAt: new Date().toISOString()
  }

  filtered.unshift(reviewData) // Add to beginning

  // Keep only last 50 reviews
  if (filtered.length > 50) {
    filtered.pop()
  }

  localStorage.setItem('quode_reviews', JSON.stringify(filtered))
}

// Check if review already exists for this PR
const checkExistingReview = () => {
  const savedReviews = JSON.parse(localStorage.getItem('quode_reviews') || '[]')
  const existing = savedReviews.find(r => r.repo === props.repo && r.prNumber === props.pr.number)

  if (existing) {
    review.value = existing.review
    prDetails.value = existing.prDetails
    return true
  }
  return false
}

const generateReview = async () => {
  loading.value = true
  loadingStage.value = 'Fetching PR details'
  aiProgress.value = ''
  error.value = null
  review.value = null

  try {
    // First, get detailed PR info with diff
    const detailsResponse = await axios.get(`/api/pr-details?repo=${encodeURIComponent(props.repo)}&number=${props.pr.number}`)
    prDetails.value = detailsResponse.data.pr

    // Small delay to show completion of first stage
    await new Promise(resolve => setTimeout(resolve, 300))

    loadingStage.value = 'AI analysing code changes'

    // Simulate AI progress indicators
    const aiSteps = [
      'Reading code diff...',
      'Identifying potential issues...',
      'Analysing code quality...',
      'Checking best practices...',
      'Generating recommendations...',
      'Finalising review...'
    ]

    let currentStep = 0
    const progressInterval = setInterval(() => {
      if (currentStep < aiSteps.length) {
        aiProgress.value = aiSteps[currentStep]
        currentStep++
      }
    }, 8000) // Update every 8 seconds

    try {
      // Then generate the AI review
      const reviewResponse = await axios.post('/api/review', {
        pr: prDetails.value,
        customGuidelines: props.customGuidelines
      })

      clearInterval(progressInterval)

      loadingStage.value = 'Finalising review'
      aiProgress.value = ''

      // Small delay to show final stage
      await new Promise(resolve => setTimeout(resolve, 500))

      review.value = reviewResponse.data.review

      // Auto-save the review
      saveReview()
    } catch (err) {
      clearInterval(progressInterval)
      throw err
    }
  } catch (err) {
    error.value = err.response?.data?.error || 'Failed to generate review'
    console.error(err)
  } finally {
    loading.value = false
  }
}

// Toggle PR details visibility
const togglePRDetails = async () => {
  if (!showPRDetails.value && !prDetails.value) {
    // Fetch PR details if not already loaded
    loadingPRDetails.value = true
    try {
      const response = await axios.get(`/api/pr-details?repo=${encodeURIComponent(props.repo)}&number=${props.pr.number}`)
      prDetails.value = response.data.pr
    } catch (err) {
      console.error('Failed to fetch PR details:', err)
      error.value = 'Failed to load PR details'
    } finally {
      loadingPRDetails.value = false
    }
  }
  showPRDetails.value = !showPRDetails.value
}

// Reset review when PR changes, but check for existing review
watch(() => props.pr, async () => {
  review.value = null
  prDetails.value = null
  error.value = null
  prInfoTab.value = 'overview'
  showPRDetails.value = false
  loadingPRDetails.value = false
  complexityData.value = null

  // Check if we have an existing review for this PR
  if (props.pr) {
    checkExistingReview()
  }
}, { immediate: true })

// Calculate complexity when PR details are loaded
watch(() => prDetails.value, () => {
  if (prDetails.value) {
    calculateComplexity()
  }
})

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Parse diff to extract added lines for each file
const parseDiffForFile = (diff, filePath) => {
  const lines = diff.split('\n')
  const addedLines = []
  let inFile = false
  let currentLine = ''

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i]

    // Check if we're in the right file
    if (line.startsWith('diff --git')) {
      inFile = line.includes(filePath)
      continue
    }

    if (inFile && line.startsWith('+') && !line.startsWith('+++')) {
      // This is an added line
      addedLines.push(line.substring(1)) // Remove the '+' prefix
    }
  }

  return addedLines.join('\n')
}

// Calculate cyclomatic complexity from code
const calculateCyclomaticComplexity = (code) => {
  let complexity = 1 // Base complexity

  // Control flow keywords that add complexity
  const patterns = [
    /\bif\s*\(/g,           // if statements
    /\belse\s+if\b/g,       // else if
    /\bfor\s*\(/g,          // for loops
    /\bwhile\s*\(/g,        // while loops
    /\bcase\s+/g,           // switch cases
    /\bcatch\s*\(/g,        // catch blocks
    /\b\?\s*.+\s*:/g,       // ternary operators
    /&&/g,                  // logical AND
    /\|\|/g,                // logical OR
  ]

  patterns.forEach(pattern => {
    const matches = code.match(pattern)
    if (matches) {
      complexity += matches.length
    }
  })

  return complexity
}

// Calculate cognitive complexity (nesting depth)
const calculateCognitiveComplexity = (code) => {
  const lines = code.split('\n')
  let maxNesting = 0
  let currentNesting = 0
  let totalNesting = 0
  let nestingLines = 0

  lines.forEach(line => {
    const trimmed = line.trim()

    // Increase nesting on opening braces or keywords
    if (trimmed.includes('{') ||
        /^(if|for|while|switch|try|catch|function|class)\s*\(/.test(trimmed)) {
      currentNesting++
      maxNesting = Math.max(maxNesting, currentNesting)
    }

    // Decrease nesting on closing braces
    if (trimmed.includes('}')) {
      currentNesting = Math.max(0, currentNesting - 1)
    }

    if (currentNesting > 0) {
      totalNesting += currentNesting
      nestingLines++
    }
  })

  const avgNesting = nestingLines > 0 ? totalNesting / nestingLines : 0
  return { maxNesting, avgNesting }
}

// Detect security-sensitive patterns
const detectSecurityPatterns = (code) => {
  const patterns = [
    { regex: /eval\s*\(/g, name: 'eval() usage', severity: 'high' },
    { regex: /innerHTML\s*=/g, name: 'innerHTML assignment', severity: 'high' },
    { regex: /dangerouslySetInnerHTML/g, name: 'dangerouslySetInnerHTML', severity: 'high' },
    { regex: /document\.write/g, name: 'document.write()', severity: 'high' },
    { regex: /new\s+Function\s*\(/g, name: 'Function constructor', severity: 'high' },
    { regex: /exec\s*\(/g, name: 'exec() call', severity: 'medium' },
    { regex: /\$\{[^}]*\}/g, name: 'Template literal interpolation', severity: 'low' },
    { regex: /localStorage|sessionStorage/g, name: 'Browser storage usage', severity: 'low' },
    { regex: /password|secret|token|apikey/gi, name: 'Potential secrets', severity: 'medium' },
  ]

  const detected = []
  patterns.forEach(({ regex, name, severity }) => {
    const matches = code.match(regex)
    if (matches) {
      detected.push({ name, count: matches.length, severity })
    }
  })

  return detected
}

// Detect code quality patterns
const detectQualityPatterns = (code) => {
  const patterns = []

  // Long functions (rough estimate by brace depth)
  const functionMatches = code.match(/function\s+\w+|const\s+\w+\s*=\s*\(/g)
  if (functionMatches && functionMatches.length > 10) {
    patterns.push({ name: 'Many functions defined', count: functionMatches.length, type: 'neutral' })
  }

  // TODO/FIXME comments
  const todoMatches = code.match(/\/\/\s*(TODO|FIXME|HACK|XXX)/gi)
  if (todoMatches) {
    patterns.push({ name: 'TODO/FIXME comments', count: todoMatches.length, type: 'warning' })
  }

  // Console logs (potential debug code)
  const consoleMatches = code.match(/console\.(log|warn|error|debug)/g)
  if (consoleMatches && consoleMatches.length > 3) {
    patterns.push({ name: 'Console statements', count: consoleMatches.length, type: 'warning' })
  }

  // Commented code
  const commentedCode = code.match(/\/\/\s*[a-zA-Z_$][a-zA-Z0-9_$]*\s*\(/g)
  if (commentedCode && commentedCode.length > 5) {
    patterns.push({ name: 'Commented code blocks', count: commentedCode.length, type: 'warning' })
  }

  return patterns
}

// Calculate complexity metrics
const calculateComplexity = () => {
  if (!prDetails.value) return

  const files = prDetails.value.files || []
  const diff = prDetails.value.diff || ''

  const fileMetrics = files.map(file => {
    const factors = []
    const path = file.path.toLowerCase()

    // Parse the actual code changes for this file
    const addedCode = parseDiffForFile(diff, file.path)

    // Calculate cyclomatic complexity
    const cyclomaticComplexity = calculateCyclomaticComplexity(addedCode)

    // Calculate cognitive complexity
    const { maxNesting, avgNesting } = calculateCognitiveComplexity(addedCode)

    // Detect security patterns
    const securityIssues = detectSecurityPatterns(addedCode)

    // Detect quality patterns
    const qualityIssues = detectQualityPatterns(addedCode)

    let complexity = 0

    // Cyclomatic Complexity Score (0-30 points)
    if (cyclomaticComplexity > 20) {
      complexity += 30
      factors.push({ label: `Very High Cyclomatic Complexity (${cyclomaticComplexity} paths)`, score: 30, type: 'high' })
    } else if (cyclomaticComplexity > 10) {
      complexity += 20
      factors.push({ label: `High Cyclomatic Complexity (${cyclomaticComplexity} paths)`, score: 20, type: 'high' })
    } else if (cyclomaticComplexity > 5) {
      complexity += 10
      factors.push({ label: `Moderate Cyclomatic Complexity (${cyclomaticComplexity} paths)`, score: 10, type: 'medium' })
    } else {
      factors.push({ label: `Low Cyclomatic Complexity (${cyclomaticComplexity} paths)`, score: 0, type: 'positive' })
    }

    // Cognitive Complexity Score (0-25 points)
    if (maxNesting > 5) {
      complexity += 25
      factors.push({ label: `Deep Nesting (max ${maxNesting} levels)`, score: 25, type: 'high' })
    } else if (maxNesting > 3) {
      complexity += 15
      factors.push({ label: `Moderate Nesting (max ${maxNesting} levels)`, score: 15, type: 'medium' })
    } else if (maxNesting > 1) {
      complexity += 5
      factors.push({ label: `Some Nesting (max ${maxNesting} levels)`, score: 5, type: 'low' })
    }

    // Security Patterns (0-30 points)
    let securityScore = 0
    securityIssues.forEach(issue => {
      const points = issue.severity === 'high' ? 10 : issue.severity === 'medium' ? 5 : 2
      securityScore += points * issue.count
      factors.push({
        label: `${issue.name} (${issue.count}x)`,
        score: points * issue.count,
        type: issue.severity === 'high' ? 'high' : 'medium'
      })
    })
    complexity += Math.min(30, securityScore)

    // Change Size Score (0-20 points)
    const totalChanges = file.additions + file.deletions
    if (totalChanges > 300) {
      complexity += 20
      factors.push({ label: `Very Large Change (${totalChanges} lines)`, score: 20, type: 'high' })
    } else if (totalChanges > 150) {
      complexity += 15
      factors.push({ label: `Large Change (${totalChanges} lines)`, score: 15, type: 'medium' })
    } else if (totalChanges > 50) {
      complexity += 8
      factors.push({ label: `Medium Change (${totalChanges} lines)`, score: 8, type: 'low' })
    } else {
      factors.push({ label: `Small Change (${totalChanges} lines)`, score: 0, type: 'positive' })
    }

    // Quality Issues
    qualityIssues.forEach(issue => {
      if (issue.type === 'warning' && issue.count > 5) {
        factors.push({ label: `${issue.name} (${issue.count})`, score: 5, type: 'medium' })
        complexity += 5
      }
    })

    // File Type Adjustments
    if (path.includes('test') || path.includes('spec')) {
      complexity = Math.max(0, complexity - 15)
      factors.push({ label: 'Test File (Lower Risk)', score: -15, type: 'positive' })
    }

    if (path.endsWith('.json') || path.endsWith('.md') || path.endsWith('.yml') || path.endsWith('.yaml')) {
      complexity = Math.max(0, complexity - 20)
      factors.push({ label: 'Config/Docs File (Lower Risk)', score: -20, type: 'positive' })
    }

    if (path.endsWith('.css') || path.endsWith('.scss') || path.endsWith('.sass')) {
      complexity = Math.max(0, complexity - 10)
      factors.push({ label: 'Stylesheet (Lower Risk)', score: -10, type: 'positive' })
    }

    // Critical file paths get a boost
    if (path.includes('auth') || path.includes('login') || path.includes('password')) {
      complexity += 10
      factors.push({ label: 'Authentication/Security File', score: 10, type: 'high' })
    }

    if (path.includes('payment') || path.includes('billing')) {
      complexity += 10
      factors.push({ label: 'Payment/Billing File', score: 10, type: 'high' })
    }

    if (path.includes('api') || path.includes('service')) {
      complexity += 5
      factors.push({ label: 'API/Service File', score: 5, type: 'medium' })
    }

    complexity = Math.max(0, Math.min(100, complexity))

    return {
      path: file.path,
      additions: file.additions,
      deletions: file.deletions,
      totalChanges,
      complexity,
      risk: complexity > 50 ? 'high' : complexity > 25 ? 'medium' : 'low',
      factors,
      expanded: false,
      metrics: {
        cyclomaticComplexity,
        maxNesting,
        avgNesting: avgNesting.toFixed(1),
        securityIssues: securityIssues.length,
        qualityIssues: qualityIssues.length
      }
    }
  })

  // Sort by complexity
  fileMetrics.sort((a, b) => b.complexity - a.complexity)

  // Calculate overall stats
  const totalAdditions = files.reduce((sum, f) => sum + f.additions, 0)
  const totalDeletions = files.reduce((sum, f) => sum + f.deletions, 0)
  const avgComplexity = fileMetrics.reduce((sum, f) => sum + f.complexity, 0) / Math.max(fileMetrics.length, 1)

  const highRiskCount = fileMetrics.filter(f => f.risk === 'high').length
  const mediumRiskCount = fileMetrics.filter(f => f.risk === 'medium').length
  const lowRiskCount = fileMetrics.filter(f => f.risk === 'low').length

  const totalCyclomatic = fileMetrics.reduce((sum, f) => sum + f.metrics.cyclomaticComplexity, 0)
  const maxNestingOverall = Math.max(...fileMetrics.map(f => f.metrics.maxNesting))

  complexityData.value = {
    files: fileMetrics,
    stats: {
      totalFiles: files.length,
      totalAdditions,
      totalDeletions,
      totalChanges: totalAdditions + totalDeletions,
      avgComplexity: Math.round(avgComplexity),
      highRiskCount,
      mediumRiskCount,
      lowRiskCount,
      totalCyclomaticComplexity: totalCyclomatic,
      maxNestingDepth: maxNestingOverall
    }
  }
}

const toggleFileExpanded = (filePath) => {
  const file = complexityData.value.files.find(f => f.path === filePath)
  if (file) {
    file.expanded = !file.expanded
  }
}
</script>

<template>
  <div class="review-panel">
    <div class="review-header">
      <h2 class="review-title">
        PR #{{ pr.number }}: {{ pr.title }}
      </h2>
      <div class="header-actions">
        <button
          class="btn-secondary"
          @click="togglePRDetails"
          :disabled="loadingPRDetails"
        >
          <span v-if="loadingPRDetails">Loading...</span>
          <span v-else>{{ showPRDetails ? 'Hide' : 'Show' }} PR Details</span>
        </button>
        <button
          class="btn"
          @click="generateReview"
          :disabled="loading"
        >
          <span v-if="loading">Reviewing...</span>
          <span v-else-if="review">Re-review</span>
          <span v-else>Review with AI</span>
        </button>
      </div>
    </div>

    <div class="review-body">
      <div v-if="error" class="error">
        {{ error }}
      </div>

      <!-- PR Details Section (collapsible) -->
      <div v-if="showPRDetails && prDetails && !loading" class="pr-details-section">
        <div class="pr-details-tabs">
          <button
            class="pr-tab"
            :class="{ active: prInfoTab === 'overview' }"
            @click="prInfoTab = 'overview'"
          >
            Overview
          </button>
          <button
            class="pr-tab"
            :class="{ active: prInfoTab === 'files' }"
            @click="prInfoTab = 'files'"
          >
            Files ({{ prDetails.files?.length || 0 }})
          </button>
          <button
            class="pr-tab"
            :class="{ active: prInfoTab === 'commits' }"
            @click="prInfoTab = 'commits'"
          >
            Commits ({{ prDetails.commits?.length || 0 }})
          </button>
          <button
            class="pr-tab"
            :class="{ active: prInfoTab === 'conversation' }"
            @click="prInfoTab = 'conversation'"
          >
            Conversation ({{ (prDetails.comments?.length || 0) + (prDetails.reviewComments?.length || 0) }})
          </button>
        </div>

        <div class="pr-details-content">
          <!-- Overview Tab -->
          <div v-if="prInfoTab === 'overview'" class="pr-overview">
            <div class="pr-meta-info">
              <div class="pr-stat">
                <span class="stat-label">State:</span>
                <span class="stat-value" :class="prDetails.state">{{ prDetails.state }}</span>
              </div>
              <div class="pr-stat">
                <span class="stat-label">Created:</span>
                <span class="stat-value">{{ formatDate(prDetails.createdAt) }}</span>
              </div>
              <div class="pr-stat">
                <span class="stat-label">Updated:</span>
                <span class="stat-value">{{ formatDate(prDetails.updatedAt) }}</span>
              </div>
              <div class="pr-stat">
                <span class="stat-label">Changes:</span>
                <span class="stat-value">+{{ prDetails.additions }} -{{ prDetails.deletions }}</span>
              </div>
              <div class="pr-stat">
                <span class="stat-label">Branch:</span>
                <span class="stat-value">{{ prDetails.headRefName }} → {{ prDetails.baseRefName }}</span>
              </div>
            </div>

            <div v-if="prDetails.body" class="pr-description">
              <h4 class="section-title">Description</h4>
              <div class="markdown-content" v-html="marked.parse(prDetails.body || '')"></div>
            </div>
          </div>

          <!-- Files Tab -->
          <div v-if="prInfoTab === 'files'" class="pr-files">
            <div v-for="file in prDetails.files" :key="file.path" class="file-item">
              <div class="file-header">
                <span class="file-path">{{ file.path }}</span>
                <span class="file-changes">+{{ file.additions }} -{{ file.deletions }}</span>
              </div>
            </div>
          </div>

          <!-- Commits Tab -->
          <div v-if="prInfoTab === 'commits'" class="pr-commits">
            <div v-for="commit in prDetails.commits" :key="commit.oid" class="commit-item">
              <div class="commit-message">{{ commit.messageHeadline }}</div>
              <div class="commit-meta">
                <span>{{ commit.authors?.[0]?.login || 'Unknown' }}</span>
                <span>•</span>
                <span>{{ commit.oid.substring(0, 7) }}</span>
              </div>
            </div>
          </div>

          <!-- Conversation Tab -->
          <div v-if="prInfoTab === 'conversation'" class="pr-conversation">
            <div v-if="!prDetails.comments?.length && !prDetails.reviewComments?.length" class="empty-conversation">
              No comments yet
            </div>

            <div v-for="comment in prDetails.comments" :key="comment.id" class="comment-item">
              <div class="comment-header">
                <strong>{{ comment.author }}</strong>
                <span class="comment-date">{{ formatDate(comment.createdAt) }}</span>
              </div>
              <div class="comment-body">{{ comment.body }}</div>
            </div>

            <div v-for="comment in prDetails.reviewComments" :key="comment.id" class="comment-item review-comment">
              <div class="comment-header">
                <strong>{{ comment.author }}</strong>
                <span class="comment-location">{{ comment.path }}:{{ comment.line }}</span>
                <span class="comment-date">{{ formatDate(comment.createdAt) }}</span>
              </div>
              <div class="comment-body">{{ comment.body }}</div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="loading" class="loading-container">
        <div class="loading-content">
          <div class="loading-icon">
            <svg class="loading-svg" viewBox="0 0 100 100">
              <defs>
                <linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                  <stop offset="0%" style="stop-color:#6366f1;stop-opacity:1" />
                  <stop offset="100%" style="stop-color:#10b981;stop-opacity:1" />
                </linearGradient>
              </defs>
              <circle class="loading-circle-bg" cx="50" cy="50" r="45"></circle>
              <circle class="loading-circle" cx="50" cy="50" r="45"></circle>
            </svg>
            <div class="loading-icon-inner">
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 11l3 3L22 4"></path>
                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
              </svg>
            </div>
          </div>
          <h3 class="loading-title">{{ loadingStage }}</h3>
          <p class="loading-subtitle">This may take 30-120 seconds depending on PR size</p>
          <div class="loading-stages">
            <div class="loading-stage" :class="{ active: loadingStage === 'Fetching PR details', complete: loadingStage !== 'Fetching PR details' }">
              <div class="stage-dot"></div>
              <span>Fetching PR details</span>
            </div>
            <div class="loading-stage" :class="{ active: loadingStage === 'AI analysing code changes', complete: loadingStage === 'Finalising review' }">
              <div class="stage-dot"></div>
              <div class="stage-content">
                <span>AI analysing code</span>
                <span v-if="aiProgress" class="ai-progress">{{ aiProgress }}</span>
              </div>
            </div>
            <div class="loading-stage" :class="{ active: loadingStage === 'Finalising review' }">
              <div class="stage-dot"></div>
              <span>Finalising review</span>
            </div>
          </div>
        </div>
      </div>

      <div v-else-if="review">
        <div class="review-meta">
          <div>
            <span>Reviewed by {{ review.model }}</span>
            <span>•</span>
            <span>{{ new Date(review.timestamp).toLocaleString() }}</span>
          </div>
          <button class="btn-secondary" @click="openModal">
            View Full Review
          </button>
        </div>

        <!-- Truncation Warning -->
        <div v-if="review.truncated" class="truncation-warning">
          <strong>⚠️ Warning:</strong> This review was cut off due to length limits.
          The AI response exceeded the maximum token limit. Consider reviewing a smaller PR or running the review again.
          <span v-if="review.finishReason"> (Reason: {{ review.finishReason }})</span>
        </div>

        <!-- Missing Sections Warning -->
        <div v-else-if="review.hasAllSections === false" class="truncation-warning" style="background: rgba(251, 146, 60, 0.1); border-color: #fb923c;">
          <strong>⚠️ Notice:</strong> Some sections may be missing from this review.
          This could mean the AI didn't find issues in those areas, or the response was incomplete.
        </div>

        <!-- Tabs -->
        <div class="tabs">
          <button
            class="tab"
            :class="{ active: activeTab === 'actionable' }"
            @click="activeTab = 'actionable'"
          >
            Action Items
          </button>
          <button
            class="tab"
            :class="{ active: activeTab === 'testScenarios' }"
            @click="activeTab = 'testScenarios'"
          >
            Test Scenarios
          </button>
          <button
            class="tab"
            :class="{ active: activeTab === 'complexity' }"
            @click="activeTab = 'complexity'"
          >
            Complexity Score
          </button>
          <button
            class="tab"
            :class="{ active: activeTab === 'chat' }"
            @click="activeTab = 'chat'"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.5rem;">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            Chat
            <span v-if="chatMessages.length > 0" class="chat-badge">{{ chatMessages.length }}</span>
          </button>
        </div>

        <!-- Tab Content -->
        <div class="tab-content">
          <div v-if="activeTab === 'actionable'">
            <div v-if="actionableHtml" class="review-content" v-html="actionableHtml"></div>
            <div v-else class="tab-empty">
              <p>No actionable items found in this section.</p>
              <p class="tab-empty-hint">The AI may have included this content in another section, or there may be no critical issues.</p>
            </div>
          </div>
          <div v-if="activeTab === 'testScenarios'">
            <div v-if="testScenariosHtml" class="review-content" v-html="testScenariosHtml"></div>
            <div v-else class="tab-empty">
              <p>No test scenarios found in this section.</p>
              <p class="tab-empty-hint">The AI may not have generated test scenarios for this PR.</p>
            </div>
          </div>
          <div v-if="activeTab === 'complexity'">
            <div v-if="complexityData" class="complexity-content">
              <!-- Overall Stats -->
              <div class="complexity-stats">
                <div class="complexity-stat-card">
                  <div class="stat-icon" style="background: linear-gradient(135deg, #6366f1, #818cf8);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                      <path d="M9 11l3 3L22 4"></path>
                      <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                    </svg>
                  </div>
                  <div class="stat-content">
                    <div class="stat-value-lg">{{ complexityData.stats.avgComplexity }}</div>
                    <div class="stat-label-lg">Avg Complexity</div>
                  </div>
                </div>
                <div class="complexity-stat-card">
                  <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #f87171);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                      <circle cx="12" cy="12" r="10"></circle>
                      <line x1="12" y1="8" x2="12" y2="12"></line>
                      <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                  </div>
                  <div class="stat-content">
                    <div class="stat-value-lg">{{ complexityData.stats.highRiskCount }}</div>
                    <div class="stat-label-lg">High Risk Files</div>
                  </div>
                </div>
                <div class="complexity-stat-card">
                  <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                      <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                      <line x1="12" y1="9" x2="12" y2="13"></line>
                      <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                  </div>
                  <div class="stat-content">
                    <div class="stat-value-lg">{{ complexityData.stats.mediumRiskCount }}</div>
                    <div class="stat-label-lg">Medium Risk Files</div>
                  </div>
                </div>
                <div class="complexity-stat-card">
                  <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #34d399);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                      <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                  </div>
                  <div class="stat-content">
                    <div class="stat-value-lg">{{ complexityData.stats.lowRiskCount }}</div>
                    <div class="stat-label-lg">Low Risk Files</div>
                  </div>
                </div>
              </div>

              <!-- File Complexity Chart -->
              <h4 class="section-title" style="margin-top: 2rem;">File-by-File Breakdown</h4>
              <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">
                Each file is analyzed for cyclomatic complexity (decision paths), cognitive complexity (nesting depth),
                security patterns, and code quality issues. Click any file to see the detailed breakdown.
              </p>
              <div class="complexity-chart">
                <div
                  v-for="file in complexityData.files"
                  :key="file.path"
                  class="complexity-bar-item"
                  :class="{ expanded: file.expanded }"
                >
                  <div class="complexity-bar-header" @click="toggleFileExpanded(file.path)">
                    <div class="complexity-bar-info">
                      <span class="complexity-file-path">{{ file.path }}</span>
                      <div class="complexity-bar-meta">
                        <span class="complexity-changes">+{{ file.additions }} -{{ file.deletions }}</span>
                        <span class="complexity-score" :class="'risk-' + file.risk">{{ Math.round(file.complexity) }}</span>
                        <svg class="expand-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                      </div>
                    </div>
                    <div class="complexity-bar-track">
                      <div
                        class="complexity-bar-fill"
                        :class="'risk-' + file.risk"
                        :style="{ width: file.complexity + '%' }"
                      ></div>
                    </div>
                  </div>

                  <!-- Expandable Breakdown -->
                  <div v-if="file.expanded" class="complexity-breakdown">
                    <h5 class="breakdown-title">Why this score?</h5>
                    <div class="breakdown-factors">
                      <div
                        v-for="(factor, idx) in file.factors"
                        :key="idx"
                        class="factor-item"
                        :class="'factor-' + factor.type"
                      >
                        <div class="factor-label">{{ factor.label }}</div>
                        <div class="factor-score">{{ factor.score > 0 ? '+' : '' }}{{ factor.score }}</div>
                      </div>
                    </div>
                    <div class="breakdown-total">
                      <span>Total Complexity Score</span>
                      <span class="total-score">{{ Math.round(file.complexity) }} / 100</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Risk Legend -->
              <div class="complexity-legend">
                <div class="legend-title">Understanding Risk Levels</div>
                <div class="legend-item">
                  <div class="legend-dot risk-high"></div>
                  <span><strong>High Risk (60+)</strong> - Large changes with significant new code. Needs thorough review and testing.</span>
                </div>
                <div class="legend-item">
                  <div class="legend-dot risk-medium"></div>
                  <span><strong>Medium Risk (30-60)</strong> - Moderate changes. Standard review process recommended.</span>
                </div>
                <div class="legend-item">
                  <div class="legend-dot risk-low"></div>
                  <span><strong>Low Risk (0-30)</strong> - Small changes or config/test files. Quick review is sufficient.</span>
                </div>
              </div>
            </div>
            <div v-else class="tab-empty">
              <p>No complexity data available.</p>
              <p class="tab-empty-hint">Complexity analysis requires PR details to be loaded first.</p>
            </div>
          </div>
          <div v-if="activeTab === 'chat'">
            <div class="chat-container">
              <div class="chat-header">
                <div class="chat-title">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                  </svg>
                  Ask questions about this review
                </div>
                <button v-if="chatMessages.length > 0" class="btn-clear-chat" @click="clearChat">
                  Clear Chat
                </button>
              </div>

              <div class="chat-messages" ref="chatMessagesContainer">
                <div v-if="chatMessages.length === 0" class="chat-empty">
                  <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                  </svg>
                  <p>Start a conversation about this code review</p>
                  <p class="chat-empty-hint">Ask questions like "Why is file X marked as high complexity?" or "Can you explain the security issue in more detail?"</p>
                </div>

                <div
                  v-for="(message, index) in chatMessages"
                  :key="index"
                  class="chat-message"
                  :class="'chat-message-' + message.role"
                >
                  <div class="chat-message-avatar">
                    <svg v-if="message.role === 'user'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                      <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <svg v-else-if="message.role === 'assistant'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <circle cx="12" cy="12" r="10"></circle>
                      <path d="M12 16v-4"></path>
                      <path d="M12 8h.01"></path>
                    </svg>
                    <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <circle cx="12" cy="12" r="10"></circle>
                      <line x1="12" y1="8" x2="12" y2="12"></line>
                      <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                  </div>
                  <div class="chat-message-content">
                    <div class="chat-message-header">
                      <span class="chat-message-role">{{ message.role === 'user' ? 'You' : message.role === 'assistant' ? 'AI' : 'Error' }}</span>
                      <span class="chat-message-time">{{ new Date(message.timestamp).toLocaleTimeString() }}</span>
                    </div>
                    <div class="chat-message-text" v-html="marked.parse(message.content)"></div>
                  </div>
                </div>

                <div v-if="chatLoading" class="chat-message chat-message-assistant">
                  <div class="chat-message-avatar">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <circle cx="12" cy="12" r="10"></circle>
                      <path d="M12 16v-4"></path>
                      <path d="M12 8h.01"></path>
                    </svg>
                  </div>
                  <div class="chat-message-content">
                    <div class="chat-typing">
                      <span></span>
                      <span></span>
                      <span></span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="chat-input-container">
                <textarea
                  v-model="chatInput"
                  class="chat-input"
                  placeholder="Ask a question about this review..."
                  rows="3"
                  @keydown.enter.prevent="!chatLoading && chatInput.trim() && sendChatMessage()"
                  :disabled="chatLoading"
                ></textarea>
                <button
                  class="btn-send-chat"
                  @click="sendChatMessage"
                  :disabled="chatLoading || !chatInput.trim()"
                >
                  <svg v-if="!chatLoading" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="22" y1="2" x2="11" y2="13"></line>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                  </svg>
                  <span v-if="!chatLoading">Send</span>
                  <span v-else>Sending...</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="empty-state">
        <div class="empty-state-icon">
          <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M12 16v-4"></path>
            <path d="M12 8h.01"></path>
          </svg>
        </div>
        <h3 class="empty-state-title">Ready to Review</h3>
        <p class="empty-state-text">Click <strong>"Review with AI"</strong> to get comprehensive analysis with complexity scoring</p>
        <div class="empty-state-features">
          <div class="feature-pill">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
            </svg>
            Complexity Analysis
          </div>
          <div class="feature-pill">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            Security Pattern Detection
          </div>
          <div class="feature-pill">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
              <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
            AI Code Review
          </div>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <Teleport to="body">
      <div v-if="showModal && review" class="modal-overlay" @click="closeModal">
        <div class="modal-content" @click.stop>
          <div class="modal-header">
            <h2 class="modal-title">
              PR #{{ pr.number }}: {{ pr.title }}
            </h2>
            <button class="modal-close" @click="closeModal">×</button>
          </div>
          <div class="modal-body">
            <div class="review-meta-modal">
              <span>Reviewed by {{ review.model }}</span>
              <span>•</span>
              <span>{{ new Date(review.timestamp).toLocaleString() }}</span>
            </div>

            <!-- Modal Tabs -->
            <div class="tabs">
              <button
                class="tab"
                :class="{ active: modalActiveTab === 'actionable' }"
                @click="modalActiveTab = 'actionable'"
              >
                Action Items
              </button>
              <button
                class="tab"
                :class="{ active: modalActiveTab === 'testScenarios' }"
                @click="modalActiveTab = 'testScenarios'"
              >
                Test Scenarios
              </button>
              <button
                class="tab"
                :class="{ active: modalActiveTab === 'complexity' }"
                @click="modalActiveTab = 'complexity'"
              >
                Complexity Score
              </button>
            </div>

            <!-- Modal Tab Content -->
            <div class="tab-content">
              <div v-if="modalActiveTab === 'actionable'">
                <div v-if="actionableHtml" class="review-content" v-html="actionableHtml"></div>
                <div v-else class="tab-empty">
                  <p>No actionable items found in this section.</p>
                  <p class="tab-empty-hint">The AI may have included this content in another section, or there may be no critical issues.</p>
                </div>
              </div>
              <div v-if="modalActiveTab === 'testScenarios'">
                <div v-if="testScenariosHtml" class="review-content" v-html="testScenariosHtml"></div>
                <div v-else class="tab-empty">
                  <p>No test scenarios found in this section.</p>
                  <p class="tab-empty-hint">The AI may not have generated test scenarios for this PR.</p>
                </div>
              </div>
              <div v-if="modalActiveTab === 'complexity'">
                <div v-if="complexityData" class="complexity-content">
                  <!-- Overall Stats -->
                  <div class="complexity-stats">
                    <div class="complexity-stat-card">
                      <div class="stat-icon" style="background: linear-gradient(135deg, #6366f1, #818cf8);">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                          <path d="M9 11l3 3L22 4"></path>
                          <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                        </svg>
                      </div>
                      <div class="stat-content">
                        <div class="stat-value-lg">{{ complexityData.stats.avgComplexity }}</div>
                        <div class="stat-label-lg">Avg Complexity</div>
                      </div>
                    </div>
                    <div class="complexity-stat-card">
                      <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #f87171);">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                          <circle cx="12" cy="12" r="10"></circle>
                          <line x1="12" y1="8" x2="12" y2="12"></line>
                          <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                      </div>
                      <div class="stat-content">
                        <div class="stat-value-lg">{{ complexityData.stats.highRiskCount }}</div>
                        <div class="stat-label-lg">High Risk Files</div>
                      </div>
                    </div>
                    <div class="complexity-stat-card">
                      <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                          <line x1="12" y1="9" x2="12" y2="13"></line>
                          <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                      </div>
                      <div class="stat-content">
                        <div class="stat-value-lg">{{ complexityData.stats.mediumRiskCount }}</div>
                        <div class="stat-label-lg">Medium Risk Files</div>
                      </div>
                    </div>
                    <div class="complexity-stat-card">
                      <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #34d399);">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                          <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                      </div>
                      <div class="stat-content">
                        <div class="stat-value-lg">{{ complexityData.stats.lowRiskCount }}</div>
                        <div class="stat-label-lg">Low Risk Files</div>
                      </div>
                    </div>
                  </div>

                  <!-- File Complexity Chart -->
                  <h4 class="section-title" style="margin-top: 2rem;">File-by-File Breakdown</h4>
                  <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">
                    Each file is analyzed for cyclomatic complexity (decision paths), cognitive complexity (nesting depth),
                    security patterns, and code quality issues. Click any file to see the detailed breakdown.
                  </p>
                  <div class="complexity-chart">
                    <div
                      v-for="file in complexityData.files"
                      :key="file.path"
                      class="complexity-bar-item"
                      :class="{ expanded: file.expanded }"
                    >
                      <div class="complexity-bar-header" @click="toggleFileExpanded(file.path)">
                        <div class="complexity-bar-info">
                          <span class="complexity-file-path">{{ file.path }}</span>
                          <div class="complexity-bar-meta">
                            <span class="complexity-changes">+{{ file.additions }} -{{ file.deletions }}</span>
                            <span class="complexity-score" :class="'risk-' + file.risk">{{ Math.round(file.complexity) }}</span>
                            <svg class="expand-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                              <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                          </div>
                        </div>
                        <div class="complexity-bar-track">
                          <div
                            class="complexity-bar-fill"
                            :class="'risk-' + file.risk"
                            :style="{ width: file.complexity + '%' }"
                          ></div>
                        </div>
                      </div>

                      <!-- Expandable Breakdown -->
                      <div v-if="file.expanded" class="complexity-breakdown">
                        <h5 class="breakdown-title">Why this score?</h5>
                        <div class="breakdown-factors">
                          <div
                            v-for="(factor, idx) in file.factors"
                            :key="idx"
                            class="factor-item"
                            :class="'factor-' + factor.type"
                          >
                            <div class="factor-label">{{ factor.label }}</div>
                            <div class="factor-score">{{ factor.score > 0 ? '+' : '' }}{{ factor.score }}</div>
                          </div>
                        </div>
                        <div class="breakdown-total">
                          <span>Total Complexity Score</span>
                          <span class="total-score">{{ Math.round(file.complexity) }} / 100</span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Risk Legend -->
                  <div class="complexity-legend">
                    <div class="legend-title">Understanding Risk Levels</div>
                    <div class="legend-item">
                      <div class="legend-dot risk-high"></div>
                      <span><strong>High Risk (60+)</strong> - Large changes with significant new code. Needs thorough review and testing.</span>
                    </div>
                    <div class="legend-item">
                      <div class="legend-dot risk-medium"></div>
                      <span><strong>Medium Risk (30-60)</strong> - Moderate changes. Standard review process recommended.</span>
                    </div>
                    <div class="legend-item">
                      <div class="legend-dot risk-low"></div>
                      <span><strong>Low Risk (0-30)</strong> - Small changes or config/test files. Quick review is sufficient.</span>
                    </div>
                  </div>
                </div>
                <div v-else class="tab-empty">
                  <p>No complexity data available.</p>
                  <p class="tab-empty-hint">Complexity analysis requires PR details to be loaded first.</p>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn" @click="closeModal">Close</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.review-panel {
  background: var(--bg-card);
  border-radius: 16px;
  box-shadow: 0 2px 12px var(--shadow);
}

.review-header {
  padding: 1.5rem 2rem;
  background: var(--bg-card);
  border-bottom: 2px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1.5rem;
}

.header-actions {
  display: flex;
  gap: 0.75rem;
  align-items: center;
}

.review-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--text);
  flex: 1;
  line-height: 1.4;
  letter-spacing: -0.02em;
}

.review-body {
  padding: 2.5rem;
  background: var(--bg-card);
  min-height: 400px;
  display: flex;
  flex-direction: column;
}

.review-body::-webkit-scrollbar {
  width: 10px;
}

.review-body::-webkit-scrollbar-track {
  background: transparent;
}

.review-body::-webkit-scrollbar-thumb {
  background: var(--border);
  border-radius: 5px;
}

.review-body::-webkit-scrollbar-thumb:hover {
  background: var(--text-muted);
}

.review-meta {
  padding: 1.25rem 1.5rem;
  background: var(--bg-tertiary);
  border-radius: 12px;
  border: 2px solid var(--border);
  margin-bottom: 2.5rem;
  font-size: 0.9rem;
  color: var(--text-muted);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  font-weight: 500;
}

.review-meta > div {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.review-meta-modal {
  padding: 1.25rem 1.5rem;
  background: var(--bg-tertiary);
  border-radius: 12px;
  border: 2px solid var(--border);
  margin-bottom: 2.5rem;
  font-size: 0.9rem;
  color: var(--text-muted);
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-weight: 500;
}

.btn-secondary {
  padding: 0.75rem 1.5rem;
  background: var(--bg-elevated);
  color: var(--text);
  border: none;
  border-radius: 12px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
  box-shadow: 0 1px 2px var(--shadow);
}

.btn-secondary:hover:not(:disabled) {
  background: #f3f4f6;
  transform: translateY(-1px);
  box-shadow: 0 2px 4px var(--shadow);
}

.btn-secondary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Professional Loading UI */
.loading-container {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 500px;
  padding: 4rem 2rem;
}

.loading-content {
  text-align: center;
  max-width: 500px;
}

.loading-icon {
  position: relative;
  width: 120px;
  height: 120px;
  margin: 0 auto 2rem;
}

.loading-svg {
  width: 100%;
  height: 100%;
  transform: rotate(-90deg);
}

.loading-circle-bg {
  fill: none;
  stroke: var(--border);
  stroke-width: 4;
}

.loading-circle {
  fill: none;
  stroke: url(#gradient);
  stroke-width: 4;
  stroke-dasharray: 283;
  stroke-dashoffset: 283;
  animation: loadingProgress 2.5s linear infinite;
  stroke-linecap: round;
}

@keyframes loadingProgress {
  0% {
    stroke-dashoffset: 283;
  }
  100% {
    stroke-dashoffset: 0;
  }
}

.loading-icon-inner {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  color: var(--primary);
  animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
    transform: translate(-50%, -50%) scale(1);
  }
  50% {
    opacity: 0.6;
    transform: translate(-50%, -50%) scale(0.95);
  }
}

.loading-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text);
  margin: 0 0 0.75rem 0;
  letter-spacing: -0.02em;
}

.loading-subtitle {
  font-size: 0.9rem;
  color: var(--text-muted);
  margin: 0 0 2.5rem 0;
  font-weight: 500;
}

.loading-stages {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-top: 2.5rem;
  text-align: left;
}

.loading-stage {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem 1.5rem;
  background: var(--bg-hover);
  border: 2px solid var(--border);
  border-radius: 12px;
  transition: all 0.3s ease;
  opacity: 0.5;
}

.loading-stage.active {
  opacity: 1;
  background: var(--bg-elevated);
  border-color: var(--primary);
  box-shadow: 0 4px 12px var(--glow);
}

.loading-stage.complete {
  opacity: 1;
  background: rgba(16, 185, 129, 0.05);
  border-color: var(--secondary);
}

.stage-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: var(--border);
  flex-shrink: 0;
  transition: all 0.3s ease;
}

.loading-stage.active .stage-dot {
  background: var(--primary);
  animation: dotPulse 1.5s ease-in-out infinite;
  box-shadow: 0 0 0 0 var(--primary);
}

.loading-stage.complete .stage-dot {
  background: var(--secondary);
  position: relative;
}

.loading-stage.complete .stage-dot::after {
  content: '✓';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  color: white;
  font-size: 8px;
  font-weight: bold;
}

@keyframes dotPulse {
  0% {
    box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.7);
  }
  70% {
    box-shadow: 0 0 0 10px rgba(99, 102, 241, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(99, 102, 241, 0);
  }
}

.loading-stage span {
  font-size: 0.95rem;
  font-weight: 500;
  color: var(--text-secondary);
  transition: color 0.3s ease;
}

.loading-stage.active span {
  color: var(--text);
  font-weight: 600;
}

.loading-stage.complete span {
  color: var(--secondary);
}

.stage-content {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  flex: 1;
}

.ai-progress {
  font-size: 0.85rem !important;
  color: var(--primary) !important;
  font-weight: 500 !important;
  font-style: italic;
  animation: fadeInProgress 0.4s ease-in;
}

@keyframes fadeInProgress {
  from {
    opacity: 0;
    transform: translateY(-5px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.truncation-warning {
  margin-bottom: 2rem;
  padding: 1.25rem 1.5rem;
  background: rgba(239, 68, 68, 0.1);
  border: 2px solid #fecaca;
  border-radius: 12px;
  color: #dc2626;
  font-size: 0.9rem;
  line-height: 1.6;
}

.truncation-warning strong {
  font-weight: 700;
}

.tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 2rem;
  border-bottom: 2px solid var(--border);
}

.tab {
  padding: 1rem 1.5rem;
  background: transparent;
  border: none;
  border-bottom: 3px solid transparent;
  color: var(--text-muted);
  font-size: 0.95rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
  bottom: -2px;
}

.tab:hover {
  color: var(--text);
  background: var(--bg-hover);
}

.tab.active {
  color: var(--primary);
  border-bottom-color: var(--primary);
}

.tab-content {
  min-height: 400px;
}

.tab-empty {
  padding: 4rem 2rem;
  text-align: center;
  color: var(--text-muted);
}

.tab-empty p {
  margin: 0.5rem 0;
  font-size: 1rem;
}

.tab-empty-hint {
  font-size: 0.875rem !important;
  color: var(--text-muted);
  opacity: 0.8;
}

.review-content {
  color: var(--text-secondary);
  line-height: 1.8;
}

.review-content :deep(h1) {
  font-size: 2rem;
  margin: 2.5rem 0 1.25rem;
  color: var(--text);
  font-weight: 700;
  letter-spacing: -0.02em;
}

.review-content :deep(h2) {
  font-size: 1.5rem;
  margin: 2rem 0 1rem;
  color: var(--primary);
  font-weight: 700;
  padding-bottom: 0.75rem;
  border-bottom: 2px solid var(--border);
  letter-spacing: -0.01em;
}

.review-content :deep(h3) {
  font-size: 1.25rem;
  margin: 1.5rem 0 1rem;
  color: var(--text);
  font-weight: 600;
}

.review-content :deep(p) {
  margin: 1rem 0;
  line-height: 1.8;
  color: var(--text-secondary);
}

.review-content :deep(code) {
  background: var(--bg-tertiary);
  padding: 0.3rem 0.6rem;
  border-radius: 8px;
  font-family: 'Monaco', 'Menlo', 'Consolas', 'Courier New', monospace;
  font-size: 0.875em;
  color: var(--primary);
  border: 1px solid var(--border);
  font-weight: 500;
}

.review-content :deep(pre) {
  background: var(--bg-tertiary);
  padding: 1.5rem;
  border-radius: 14px;
  overflow-x: auto;
  margin: 1.5rem 0;
  border: 2px solid var(--border);
}

.review-content :deep(pre code) {
  background: none;
  padding: 0;
  color: var(--text);
  font-size: 0.875rem;
  line-height: 1.6;
  border: none;
}

.review-content :deep(ul) {
  margin: 1rem 0;
  padding-left: 2rem;
  list-style: none;
}

.review-content :deep(ul li) {
  margin: 0.75rem 0;
  line-height: 1.7;
  position: relative;
  padding-left: 1.5rem;
}

.review-content :deep(ul li::before) {
  content: "▸";
  position: absolute;
  left: 0;
  color: var(--primary);
  font-weight: bold;
}

.review-content :deep(ol) {
  margin: 1rem 0;
  padding-left: 2rem;
  counter-reset: item;
}

.review-content :deep(ol li) {
  margin: 0.75rem 0;
  line-height: 1.7;
  position: relative;
  padding-left: 1.5rem;
  counter-increment: item;
}

.review-content :deep(ol li::before) {
  content: counter(item) ".";
  position: absolute;
  left: 0;
  color: var(--primary);
  font-weight: bold;
}

.review-content :deep(strong) {
  color: var(--text);
  font-weight: 700;
}

.review-content :deep(em) {
  color: var(--text-secondary);
  font-style: italic;
}

.review-content :deep(blockquote) {
  border-left: 4px solid var(--primary);
  padding: 1.25rem 1.75rem;
  margin: 1.5rem 0;
  background: var(--bg-tertiary);
  border-radius: 0 12px 12px 0;
  color: var(--text-secondary);
}

.review-content :deep(hr) {
  border: none;
  border-top: 2px solid var(--border);
  margin: 2rem 0;
}

.review-content :deep(table) {
  width: 100%;
  border-collapse: collapse;
  margin: 1.5rem 0;
  background: var(--bg-tertiary);
  border-radius: 12px;
  overflow: hidden;
  border: 2px solid var(--border);
}

.review-content :deep(th) {
  background: var(--bg-hover);
  padding: 0.875rem 1.25rem;
  text-align: left;
  font-weight: 700;
  color: var(--text);
  border-bottom: 2px solid var(--border);
}

.review-content :deep(td) {
  padding: 0.875rem 1.25rem;
  border-bottom: 1px solid var(--border);
  color: var(--text-secondary);
}

.review-content :deep(tr:last-child td) {
  border-bottom: none;
}

/* Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(15, 23, 42, 0.75);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 2rem;
  animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.modal-content {
  background: var(--bg-card);
  border-radius: 20px;
  box-shadow: 0 20px 60px rgba(15, 23, 42, 0.3);
  width: 100%;
  max-width: 1200px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  animation: slideUp 0.3s ease;
}

@keyframes slideUp {
  from {
    transform: translateY(20px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.modal-header {
  padding: 2rem 2.5rem;
  border-bottom: 2px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 2rem;
}

.modal-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text);
  flex: 1;
  line-height: 1.4;
  letter-spacing: -0.02em;
}

.modal-close {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  border: 2px solid var(--border);
  background: var(--bg-hover);
  color: var(--text-secondary);
  font-size: 2rem;
  line-height: 1;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.modal-close:hover {
  background: var(--bg-tertiary);
  border-color: var(--primary);
  color: var(--text);
  transform: rotate(90deg);
}

.modal-body {
  flex: 1;
  overflow-y: auto;
  padding: 2.5rem;
}

.modal-body::-webkit-scrollbar {
  width: 12px;
}

.modal-body::-webkit-scrollbar-track {
  background: var(--bg-tertiary);
  border-radius: 6px;
}

.modal-body::-webkit-scrollbar-thumb {
  background: var(--border);
  border-radius: 6px;
}

.modal-body::-webkit-scrollbar-thumb:hover {
  background: var(--text-muted);
}

.modal-footer {
  padding: 1.5rem 2.5rem;
  border-top: 2px solid var(--border);
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
  background: var(--bg-hover);
  border-radius: 0 0 20px 20px;
}

/* PR Details Section */
.pr-details-section {
  margin-bottom: 2.5rem;
  background: var(--bg-card);
  border: 2px solid var(--border);
  border-radius: 14px;
  overflow: hidden;
}

.pr-details-tabs {
  display: flex;
  gap: 0;
  background: var(--bg-hover);
  border-bottom: 2px solid var(--border);
  padding: 0 1.5rem;
}

.pr-tab {
  padding: 0.625rem 1rem;
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  color: var(--text-muted);
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
  bottom: -2px;
}

.pr-tab:hover {
  color: var(--text);
  background: rgba(99, 102, 241, 0.05);
}

.pr-tab.active {
  color: var(--primary);
  border-bottom-color: var(--primary);
  background: var(--bg-card);
}

.pr-details-content {
  padding: 1.5rem;
  max-height: 400px;
  overflow-y: auto;
}

.pr-overview .pr-meta-info {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 1.5rem;
  padding: 0.875rem 1rem;
  background: var(--bg-hover);
  border-radius: 8px;
  border: 1px solid var(--border);
}

.pr-stat {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.stat-label {
  font-size: 0.7rem;
  color: var(--text-muted);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.stat-value {
  font-size: 0.8rem;
  color: var(--text);
  font-weight: 600;
}

.stat-value.OPEN {
  color: var(--secondary);
}

.stat-value.MERGED {
  color: var(--primary);
}

.stat-value.CLOSED {
  color: #dc2626;
}

.section-title {
  font-size: 1rem;
  font-weight: 700;
  color: var(--text);
  margin-bottom: 1rem;
}

.pr-description {
  margin-top: 2rem;
}

.markdown-content {
  color: var(--text-secondary);
  line-height: 1.7;
}

.pr-files, .pr-commits, .pr-conversation {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.file-item {
  padding: 1rem 1.25rem;
  background: var(--bg-hover);
  border: 1px solid var(--border);
  border-radius: 10px;
  transition: all 0.2s;
}

.file-item:hover {
  background: var(--bg-elevated);
  border-color: var(--primary-light);
}

.file-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.file-path {
  font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
  font-size: 0.875rem;
  color: var(--text);
  font-weight: 500;
}

.file-changes {
  font-size: 0.85rem;
  color: var(--text-muted);
  font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
}

.commit-item {
  padding: 1rem 1.25rem;
  background: var(--bg-hover);
  border: 1px solid var(--border);
  border-radius: 10px;
  transition: all 0.2s;
}

.commit-item:hover {
  background: var(--bg-elevated);
  border-color: var(--primary-light);
}

.commit-message {
  font-size: 0.95rem;
  color: var(--text);
  font-weight: 500;
  margin-bottom: 0.5rem;
}

.commit-meta {
  font-size: 0.8rem;
  color: var(--text-muted);
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.comment-item {
  padding: 1.25rem;
  background: var(--bg-hover);
  border: 1px solid var(--border);
  border-radius: 10px;
  transition: all 0.2s;
}

.comment-item.review-comment {
  border-left: 4px solid var(--primary);
}

.comment-header {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  margin-bottom: 0.75rem;
  font-size: 0.875rem;
}

.comment-header strong {
  color: var(--text);
}

.comment-date {
  color: var(--text-muted);
  font-size: 0.8rem;
}

.comment-location {
  font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
  font-size: 0.75rem;
  color: var(--primary);
  background: rgba(99, 102, 241, 0.1);
  padding: 0.25rem 0.5rem;
  border-radius: 6px;
}

.comment-body {
  color: var(--text-secondary);
  line-height: 1.6;
  white-space: pre-wrap;
  word-wrap: break-word;
}

.empty-conversation {
  padding: 3rem;
  text-align: center;
  color: var(--text-muted);
  font-size: 0.95rem;
}

/* Enhanced Empty State */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  text-align: center;
  flex: 1;
}

.empty-state-icon {
  width: 80px;
  height: 80px;
  margin-bottom: 1.5rem;
  color: var(--primary);
  opacity: 0.8;
  animation: float 3s ease-in-out infinite;
}

@keyframes float {
  0%, 100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-10px);
  }
}

.empty-state-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--text);
  margin-bottom: 1rem;
  letter-spacing: -0.02em;
}

.empty-state-text {
  font-size: 1rem;
  color: var(--text-secondary);
  margin-bottom: 2rem;
  max-width: 500px;
  line-height: 1.6;
}

.empty-state-text strong {
  color: var(--primary);
  font-weight: 600;
}

.empty-state-features {
  display: flex;
  gap: 1rem;
  margin-bottom: 2rem;
  flex-wrap: wrap;
  justify-content: center;
}

.feature-pill {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.625rem 1rem;
  background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(16, 185, 129, 0.05));
  border: 1px solid var(--border);
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 500;
  color: var(--text);
  transition: all 0.2s;
}

.feature-pill:hover {
  background: linear-gradient(135deg, rgba(99, 102, 241, 0.12), rgba(16, 185, 129, 0.08));
  border-color: var(--primary-light);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px var(--shadow);
}

.feature-pill svg {
  color: var(--primary);
  flex-shrink: 0;
}

.empty-state-hint {
  font-size: 0.875rem;
  color: var(--text-muted);
  max-width: 450px;
  line-height: 1.5;
  margin-top: 1rem;
  padding: 1rem 1.5rem;
  background: var(--bg-hover);
  border-radius: 10px;
  border: 1px solid var(--border);
}

.empty-state-hint strong {
  color: var(--text);
  font-weight: 600;
}

/* Complexity Visualization Styles */
.pr-complexity {
  padding: 0.5rem 0;
}

.complexity-content {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.complexity-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 1rem;
}

.complexity-stat-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 1.25rem;
  background: var(--bg-hover);
  border: 2px solid var(--border);
  border-radius: 12px;
  transition: all 0.3s ease;
}

.complexity-stat-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 4px 16px var(--shadow);
  border-color: var(--primary-light);
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 0.75rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.stat-content {
  text-align: center;
}

.stat-value-lg {
  font-size: 2rem;
  font-weight: 800;
  color: var(--text);
  line-height: 1;
  margin-bottom: 0.5rem;
}

.stat-label-lg {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.complexity-chart {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.complexity-bar-item {
  display: flex;
  flex-direction: column;
  border: 2px solid var(--border);
  border-radius: 12px;
  padding: 1rem;
  background: var(--bg-hover);
  transition: all 0.3s ease;
}

.complexity-bar-item:hover {
  border-color: var(--primary-light);
  box-shadow: 0 2px 8px var(--shadow);
}

.complexity-bar-item.expanded {
  background: var(--bg-elevated);
  border-color: var(--primary);
}

.complexity-bar-header {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  cursor: pointer;
}

.complexity-bar-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
}

.complexity-file-path {
  font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
  font-size: 0.85rem;
  color: var(--text);
  font-weight: 500;
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.complexity-bar-meta {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-shrink: 0;
}

.expand-icon {
  color: var(--text-muted);
  transition: transform 0.3s ease;
  flex-shrink: 0;
}

.complexity-bar-item.expanded .expand-icon {
  transform: rotate(180deg);
  color: var(--primary);
}

.complexity-changes {
  font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
  font-size: 0.75rem;
  color: var(--text-muted);
  font-weight: 500;
}

.complexity-score {
  font-size: 0.8rem;
  font-weight: 700;
  padding: 0.25rem 0.625rem;
  border-radius: 8px;
  min-width: 35px;
  text-align: center;
}

.complexity-score.risk-high {
  background: rgba(239, 68, 68, 0.15);
  color: #dc2626;
  border: 1px solid rgba(239, 68, 68, 0.3);
}

.complexity-score.risk-medium {
  background: rgba(245, 158, 11, 0.15);
  color: #d97706;
  border: 1px solid rgba(245, 158, 11, 0.3);
}

.complexity-score.risk-low {
  background: rgba(16, 185, 129, 0.15);
  color: #059669;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.complexity-bar-track {
  height: 24px;
  background: var(--bg-tertiary);
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid var(--border);
  position: relative;
}

.complexity-bar-fill {
  height: 100%;
  border-radius: 7px;
  transition: width 0.6s ease, background 0.3s ease;
  position: relative;
  overflow: hidden;
}

.complexity-bar-fill::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
  animation: shimmer 2s infinite;
}

@keyframes shimmer {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(100%);
  }
}

.complexity-bar-fill.risk-high {
  background: linear-gradient(90deg, #ef4444, #dc2626);
  box-shadow: 0 0 8px rgba(239, 68, 68, 0.4);
}

.complexity-bar-fill.risk-medium {
  background: linear-gradient(90deg, #f59e0b, #d97706);
  box-shadow: 0 0 8px rgba(245, 158, 11, 0.4);
}

.complexity-bar-fill.risk-low {
  background: linear-gradient(90deg, #10b981, #059669);
  box-shadow: 0 0 8px rgba(16, 185, 129, 0.4);
}

.complexity-legend {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 1.25rem;
  background: var(--bg-hover);
  border: 1px solid var(--border);
  border-radius: 10px;
  margin-top: 1rem;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.85rem;
  color: var(--text-secondary);
}

.legend-dot {
  width: 16px;
  height: 16px;
  border-radius: 4px;
  flex-shrink: 0;
}

.legend-dot.risk-high {
  background: linear-gradient(135deg, #ef4444, #dc2626);
  box-shadow: 0 2px 6px rgba(239, 68, 68, 0.3);
}

.legend-dot.risk-medium {
  background: linear-gradient(135deg, #f59e0b, #d97706);
  box-shadow: 0 2px 6px rgba(245, 158, 11, 0.3);
}

.legend-dot.risk-low {
  background: linear-gradient(135deg, #10b981, #059669);
  box-shadow: 0 2px 6px rgba(16, 185, 129, 0.3);
}

.legend-title {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--text);
  margin-bottom: 0.5rem;
}

/* Complexity Breakdown Styles */
.complexity-breakdown {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 2px solid var(--border);
  animation: slideDown 0.3s ease;
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.breakdown-title {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--text);
  margin-bottom: 1rem;
}

.breakdown-factors {
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
  margin-bottom: 1rem;
}

.factor-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1rem;
  background: var(--bg-tertiary);
  border-radius: 8px;
  border-left: 4px solid var(--border);
  transition: all 0.2s;
}

.factor-item:hover {
  transform: translateX(3px);
  box-shadow: 0 2px 6px var(--shadow);
}

.factor-item.factor-high {
  border-left-color: #ef4444;
  background: rgba(239, 68, 68, 0.08);
}

.factor-item.factor-medium {
  border-left-color: #f59e0b;
  background: rgba(245, 158, 11, 0.08);
}

.factor-item.factor-low {
  border-left-color: #6366f1;
  background: rgba(99, 102, 241, 0.05);
}

.factor-item.factor-positive {
  border-left-color: #10b981;
  background: rgba(16, 185, 129, 0.08);
}

.factor-item.factor-neutral {
  border-left-color: var(--text-muted);
  background: var(--bg-hover);
}

.factor-label {
  font-size: 0.875rem;
  color: var(--text);
  font-weight: 500;
}

.factor-score {
  font-size: 0.875rem;
  font-weight: 700;
  font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
  padding: 0.25rem 0.625rem;
  border-radius: 6px;
  background: var(--bg-card);
  border: 1px solid var(--border);
}

.factor-item.factor-high .factor-score {
  color: #dc2626;
  background: rgba(239, 68, 68, 0.15);
  border-color: rgba(239, 68, 68, 0.3);
}

.factor-item.factor-medium .factor-score {
  color: #d97706;
  background: rgba(245, 158, 11, 0.15);
  border-color: rgba(245, 158, 11, 0.3);
}

.factor-item.factor-low .factor-score {
  color: #6366f1;
  background: rgba(99, 102, 241, 0.15);
  border-color: rgba(99, 102, 241, 0.3);
}

.factor-item.factor-positive .factor-score {
  color: #059669;
  background: rgba(16, 185, 129, 0.15);
  border-color: rgba(16, 185, 129, 0.3);
}

.breakdown-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.25rem;
  background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(16, 185, 129, 0.05));
  border-radius: 8px;
  border: 2px solid var(--primary);
  margin-top: 0.5rem;
  font-weight: 600;
}

.breakdown-total span {
  font-size: 0.95rem;
  color: var(--text);
}

.total-score {
  font-size: 1.25rem !important;
  font-weight: 800 !important;
  color: var(--primary) !important;
  font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
}

/* Chat Styles */
.chat-badge {
  margin-left: 0.5rem;
  background: var(--secondary);
  color: white;
  padding: 0.125rem 0.5rem;
  border-radius: 12px;
  font-size: 0.7rem;
  font-weight: 700;
}

.chat-container {
  display: flex;
  flex-direction: column;
  height: 600px;
  background: var(--bg-card);
  border-radius: 14px;
  border: 2px solid var(--border);
  overflow: hidden;
}

.chat-header {
  padding: 1.25rem 1.5rem;
  background: var(--bg-hover);
  border-bottom: 2px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.chat-title {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 1rem;
  font-weight: 600;
  color: var(--text);
}

.chat-title svg {
  color: var(--primary);
}

.btn-clear-chat {
  padding: 0.5rem 1rem;
  background: #fef2f2;
  color: #dc2626;
  border: 2px solid #fecaca;
  border-radius: 8px;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-clear-chat:hover {
  background: #fee2e2;
  transform: translateY(-1px);
}

.chat-messages {
  flex: 1;
  overflow-y: auto;
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.chat-messages::-webkit-scrollbar {
  width: 8px;
}

.chat-messages::-webkit-scrollbar-track {
  background: transparent;
}

.chat-messages::-webkit-scrollbar-thumb {
  background: var(--border);
  border-radius: 4px;
}

.chat-empty {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  color: var(--text-muted);
  gap: 1rem;
  padding: 3rem;
}

.chat-empty svg {
  color: var(--primary);
  opacity: 0.5;
}

.chat-empty p {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
}

.chat-empty-hint {
  font-size: 0.875rem !important;
  font-weight: 400 !important;
  opacity: 0.8;
  max-width: 400px;
}

.chat-message {
  display: flex;
  gap: 1rem;
  animation: messageSlideIn 0.3s ease;
}

@keyframes messageSlideIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.chat-message-user {
  align-self: flex-end;
  flex-direction: row-reverse;
}

.chat-message-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  flex-shrink: 0;
  box-shadow: 0 2px 8px var(--glow);
}

.chat-message-user .chat-message-avatar {
  background: linear-gradient(135deg, var(--secondary), #059669);
}

.chat-message-error .chat-message-avatar {
  background: linear-gradient(135deg, #ef4444, #dc2626);
}

.chat-message-content {
  flex: 1;
  max-width: 75%;
}

.chat-message-user .chat-message-content {
  text-align: right;
}

.chat-message-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.5rem;
}

.chat-message-user .chat-message-header {
  justify-content: flex-end;
  flex-direction: row-reverse;
}

.chat-message-role {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--text);
}

.chat-message-time {
  font-size: 0.75rem;
  color: var(--text-muted);
}

.chat-message-text {
  padding: 1rem 1.25rem;
  background: var(--bg-hover);
  border-radius: 14px;
  font-size: 0.95rem;
  line-height: 1.7;
  color: var(--text-secondary);
  border: 2px solid var(--border);
}

.chat-message-user .chat-message-text {
  background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(16, 185, 129, 0.08));
  border-color: var(--primary-light);
  text-align: left;
}

.chat-message-error .chat-message-text {
  background: rgba(239, 68, 68, 0.1);
  border-color: #fecaca;
  color: #dc2626;
}

.chat-message-text :deep(p) {
  margin: 0.5rem 0;
}

.chat-message-text :deep(p:first-child) {
  margin-top: 0;
}

.chat-message-text :deep(p:last-child) {
  margin-bottom: 0;
}

.chat-message-text :deep(code) {
  background: var(--bg-tertiary);
  padding: 0.2rem 0.4rem;
  border-radius: 4px;
  font-size: 0.875em;
}

.chat-message-text :deep(pre) {
  background: var(--bg-tertiary);
  padding: 1rem;
  border-radius: 8px;
  overflow-x: auto;
  margin: 0.75rem 0;
}

.chat-typing {
  padding: 1rem 1.25rem;
  background: var(--bg-hover);
  border-radius: 14px;
  border: 2px solid var(--border);
  display: flex;
  gap: 0.5rem;
  width: fit-content;
}

.chat-typing span {
  width: 8px;
  height: 8px;
  background: var(--primary);
  border-radius: 50%;
  animation: typingBounce 1.4s infinite;
}

.chat-typing span:nth-child(2) {
  animation-delay: 0.2s;
}

.chat-typing span:nth-child(3) {
  animation-delay: 0.4s;
}

@keyframes typingBounce {
  0%, 60%, 100% {
    transform: translateY(0);
    opacity: 1;
  }
  30% {
    transform: translateY(-10px);
    opacity: 0.7;
  }
}

.chat-input-container {
  padding: 1.25rem 1.5rem;
  background: var(--bg-hover);
  border-top: 2px solid var(--border);
  display: flex;
  gap: 1rem;
  align-items: flex-end;
}

.chat-input {
  flex: 1;
  padding: 0.75rem 1rem;
  background: var(--bg-card);
  border: 2px solid var(--border);
  border-radius: 10px;
  color: var(--text);
  font-size: 0.95rem;
  font-family: inherit;
  line-height: 1.5;
  resize: none;
  transition: all 0.2s;
}

.chat-input:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.chat-input:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.chat-input::placeholder {
  color: var(--text-muted);
}

.btn-send-chat {
  padding: 0.75rem 1.5rem;
  background: var(--primary);
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 0.95rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  box-shadow: 0 2px 8px var(--glow);
  white-space: nowrap;
}

.btn-send-chat:hover:not(:disabled) {
  background: var(--primary-dark);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px var(--glow);
}

.btn-send-chat:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}
</style>
