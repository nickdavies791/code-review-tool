<script setup>
import { ref, watch, computed } from 'vue'
import axios from 'axios'
import { marked } from 'marked'

const props = defineProps({
  pr: Object,
  repo: String
})

const loading = ref(false)
const error = ref(null)
const review = ref(null)
const prDetails = ref(null)
const showModal = ref(false)
const activeTab = ref('actionable')
const modalActiveTab = ref('actionable')
const showRawContent = ref(false)

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
    quality: '',
    highlights: '',
    summary: ''
  }

  // Split content by section markers
  const actionableMatch = content.match(/## SECTION: ACTIONABLE_ITEMS([\s\S]*?)(?=## SECTION:|$)/i)
  const qualityMatch = content.match(/## SECTION: CODE_QUALITY([\s\S]*?)(?=## SECTION:|$)/i)
  const highlightsMatch = content.match(/## SECTION: POSITIVE_HIGHLIGHTS([\s\S]*?)(?=## SECTION:|$)/i)
  const summaryMatch = content.match(/## SECTION: SUMMARY([\s\S]*?)$/i)

  if (actionableMatch) sections.actionable = actionableMatch[1].trim()
  if (qualityMatch) sections.quality = qualityMatch[1].trim()
  if (highlightsMatch) sections.highlights = highlightsMatch[1].trim()
  if (summaryMatch) sections.summary = summaryMatch[1].trim()

  return sections
})

const actionableHtml = computed(() => {
  if (!parseSections.value?.actionable) return ''
  return marked.parse(parseSections.value.actionable)
})

const qualityHtml = computed(() => {
  if (!parseSections.value?.quality) return ''
  return marked.parse(parseSections.value.quality)
})

const highlightsHtml = computed(() => {
  if (!parseSections.value?.highlights) return ''
  return marked.parse(parseSections.value.highlights)
})

const summaryHtml = computed(() => {
  if (!parseSections.value?.summary) return ''
  return marked.parse(parseSections.value.summary)
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
  error.value = null
  review.value = null

  try {
    // First, get detailed PR info with diff
    const detailsResponse = await axios.get(`/api/pr-details?repo=${encodeURIComponent(props.repo)}&number=${props.pr.number}`)
    prDetails.value = detailsResponse.data.pr

    // Then generate the AI review
    const reviewResponse = await axios.post('/api/review', {
      pr: prDetails.value
    })

    review.value = reviewResponse.data.review

    // Auto-save the review
    saveReview()
  } catch (err) {
    error.value = err.response?.data?.error || 'Failed to generate review'
    console.error(err)
  } finally {
    loading.value = false
  }
}

// Reset review when PR changes, but check for existing review
watch(() => props.pr, () => {
  review.value = null
  prDetails.value = null
  error.value = null

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

    <div class="review-body">
      <div v-if="error" class="error">
        {{ error }}
      </div>

      <div v-if="loading" class="loading">
        <div class="spinner"></div>
        <p>Analyzing code changes...</p>
      </div>

      <div v-else-if="review">
        <div class="review-meta">
          <div>
            <span>Reviewed by {{ review.model }}</span>
            <span>•</span>
            <span>{{ new Date(review.timestamp).toLocaleString() }}</span>
          </div>
          <div style="display: flex; gap: 0.75rem;">
            <button class="btn-secondary btn-small" @click="showRawContent = !showRawContent" title="Toggle raw content for debugging">
              {{ showRawContent ? 'Hide Raw' : 'Show Raw' }}
            </button>
            <button class="btn-secondary" @click="openModal">
              View Full Review
            </button>
          </div>
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

        <!-- Raw Content Debug View -->
        <div v-if="showRawContent" class="raw-content-debug">
          <h4>Raw AI Response (for debugging):</h4>
          <div class="debug-meta">
            <span><strong>Truncated:</strong> {{ review.truncated ? 'Yes' : 'No' }}</span>
            <span v-if="review.finishReason"><strong>Finish Reason:</strong> {{ review.finishReason }}</span>
            <span><strong>Has All Sections:</strong> {{ review.hasAllSections ? 'Yes' : 'No' }}</span>
          </div>
          <pre>{{ review.content }}</pre>
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
            :class="{ active: activeTab === 'quality' }"
            @click="activeTab = 'quality'"
          >
            Code Quality
          </button>
          <button
            class="tab"
            :class="{ active: activeTab === 'highlights' }"
            @click="activeTab = 'highlights'"
          >
            Highlights
          </button>
          <button
            class="tab"
            :class="{ active: activeTab === 'summary' }"
            @click="activeTab = 'summary'"
          >
            Summary
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
          <div v-if="activeTab === 'quality'">
            <div v-if="qualityHtml" class="review-content" v-html="qualityHtml"></div>
            <div v-else class="tab-empty">
              <p>No code quality analysis found in this section.</p>
            </div>
          </div>
          <div v-if="activeTab === 'highlights'">
            <div v-if="highlightsHtml" class="review-content" v-html="highlightsHtml"></div>
            <div v-else class="tab-empty">
              <p>No positive highlights found in this section.</p>
            </div>
          </div>
          <div v-if="activeTab === 'summary'">
            <div v-if="summaryHtml" class="review-content" v-html="summaryHtml"></div>
            <div v-else class="tab-empty">
              <p>No summary found in this section.</p>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="empty-state">
        <h3>Ready to Review</h3>
        <p>Click "Review with AI" to start the code review</p>
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
                :class="{ active: modalActiveTab === 'quality' }"
                @click="modalActiveTab = 'quality'"
              >
                Code Quality
              </button>
              <button
                class="tab"
                :class="{ active: modalActiveTab === 'highlights' }"
                @click="modalActiveTab = 'highlights'"
              >
                Highlights
              </button>
              <button
                class="tab"
                :class="{ active: modalActiveTab === 'summary' }"
                @click="modalActiveTab = 'summary'"
              >
                Summary
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
              <div v-if="modalActiveTab === 'quality'">
                <div v-if="qualityHtml" class="review-content" v-html="qualityHtml"></div>
                <div v-else class="tab-empty">
                  <p>No code quality analysis found in this section.</p>
                </div>
              </div>
              <div v-if="modalActiveTab === 'highlights'">
                <div v-if="highlightsHtml" class="review-content" v-html="highlightsHtml"></div>
                <div v-else class="tab-empty">
                  <p>No positive highlights found in this section.</p>
                </div>
              </div>
              <div v-if="modalActiveTab === 'summary'">
                <div v-if="summaryHtml" class="review-content" v-html="summaryHtml"></div>
                <div v-else class="tab-empty">
                  <p>No summary found in this section.</p>
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
  padding: 2rem 2.5rem;
  background: var(--bg-card);
  border-bottom: 2px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1.5rem;
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
  border: 2px solid var(--border);
  border-radius: 12px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.btn-secondary:hover:not(:disabled) {
  background: var(--bg-hover);
  border-color: var(--primary);
  transform: translateY(-1px);
  box-shadow: 0 2px 8px var(--shadow);
}

.btn-secondary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-small {
  padding: 0.5rem 1rem !important;
  font-size: 0.8rem !important;
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

.raw-content-debug {
  margin-bottom: 2rem;
  padding: 1.5rem;
  background: var(--bg-tertiary);
  border: 2px solid var(--border);
  border-radius: 12px;
}

.raw-content-debug h4 {
  margin: 0 0 1rem 0;
  color: var(--text);
  font-size: 0.9rem;
  font-weight: 600;
}

.debug-meta {
  display: flex;
  gap: 1.5rem;
  margin-bottom: 1rem;
  padding: 0.75rem 1rem;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 8px;
  font-size: 0.8rem;
  color: var(--text-secondary);
  flex-wrap: wrap;
}

.debug-meta span {
  display: flex;
  gap: 0.5rem;
}

.debug-meta strong {
  color: var(--text);
  font-weight: 600;
}

.raw-content-debug pre {
  margin: 0;
  padding: 1rem;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 8px;
  overflow-x: auto;
  white-space: pre-wrap;
  word-wrap: break-word;
  font-family: 'Monaco', 'Menlo', 'Consolas', 'Courier New', monospace;
  font-size: 0.75rem;
  line-height: 1.5;
  color: var(--text-secondary);
  max-height: 400px;
  overflow-y: auto;
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
</style>
