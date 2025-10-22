<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import { marked } from 'marked'
import ChatInterface from './ChatInterface.vue'
import PRDetailsPanel from './PRDetailsPanel.vue'
import ReviewContent from './ReviewContent.vue'

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
const showPRDetails = ref(false)
const loadingPRDetails = ref(false)
const showChat = ref(false)

const openModal = () => {
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
}

// Configure marked for better code review rendering
marked.setOptions({
  breaks: true,
  gfm: true
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
  showPRDetails.value = false
  loadingPRDetails.value = false
  showChat.value = false

  // Check if we have an existing review for this PR
  if (props.pr) {
    checkExistingReview()
  }
}, { immediate: true })
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
      <PRDetailsPanel
        v-if="showPRDetails && prDetails && !loading"
        :prDetails="prDetails"
      />

      <div v-if="loading" class="loading-container">
        <div class="loading-content">
          <div class="simple-spinner"></div>
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

        <!-- Review Content -->
        <ReviewContent :review="review" />

        <!-- Floating Chat Button -->
        <button class="floating-chat-btn" @click="showChat = !showChat" :class="{ active: showChat }">
          <svg v-if="!showChat" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
          </svg>
          <svg v-else width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>

        <!-- Chat Drawer -->
        <Transition name="slide">
          <div v-if="showChat" class="chat-drawer">
            <div class="chat-drawer-header">
              <h3>Ask Questions</h3>
              <button class="close-drawer" @click="showChat = false">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <line x1="18" y1="6" x2="6" y2="18"></line>
                  <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
              </button>
            </div>
            <div class="chat-drawer-body">
              <ChatInterface :review="review" :prDetails="prDetails" />
            </div>
          </div>
        </Transition>
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
        <p class="empty-state-text">Click <strong>"Review with AI"</strong> to get comprehensive code analysis</p>
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

            <!-- Modal Content -->
            <ReviewContent :review="review" />
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

.simple-spinner {
  width: 60px;
  height: 60px;
  margin: 0 auto 2rem;
  border: 4px solid var(--border);
  border-top-color: var(--primary);
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
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

/* Floating Chat Button */
.floating-chat-btn {
  position: fixed;
  bottom: 2rem;
  right: 2rem;
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  color: white;
  border: none;
  box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
  z-index: 100;
}

.floating-chat-btn:hover {
  transform: translateY(-2px) scale(1.05);
  box-shadow: 0 6px 25px rgba(99, 102, 241, 0.5);
}

.floating-chat-btn.active {
  background: linear-gradient(135deg, #ef4444, #dc2626);
  box-shadow: 0 4px 20px rgba(239, 68, 68, 0.4);
}

.floating-chat-btn svg {
  flex-shrink: 0;
}

/* Chat Drawer */
.chat-drawer {
  position: fixed;
  top: 0;
  right: 0;
  width: 500px;
  max-width: 90vw;
  height: 100vh;
  background: var(--bg-card);
  box-shadow: -4px 0 20px rgba(0, 0, 0, 0.15);
  z-index: 99;
  display: flex;
  flex-direction: column;
}

.chat-drawer-header {
  padding: 1.5rem 2rem;
  background: var(--bg-hover);
  border-bottom: 2px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.chat-drawer-header h3 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--text);
}

.close-drawer {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  border: none;
  background: transparent;
  color: var(--text-muted);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.close-drawer:hover {
  background: var(--bg-tertiary);
  color: var(--text);
}

.chat-drawer-body {
  flex: 1;
  overflow: hidden;
  padding: 1.5rem;
}

/* Slide Transition */
.slide-enter-active,
.slide-leave-active {
  transition: transform 0.3s ease;
}

.slide-enter-from,
.slide-leave-to {
  transform: translateX(100%);
}

.slide-enter-to,
.slide-leave-from {
  transform: translateX(0);
}

.error {
  padding: 1.25rem 1.5rem;
  background: rgba(239, 68, 68, 0.1);
  border: 2px solid #fecaca;
  border-radius: 12px;
  color: #dc2626;
  font-size: 0.9rem;
  margin-bottom: 2rem;
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
</style>
