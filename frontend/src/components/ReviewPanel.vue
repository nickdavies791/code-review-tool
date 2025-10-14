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

// Configure marked for better code review rendering
marked.setOptions({
  breaks: true,
  gfm: true
})

const reviewHtml = computed(() => {
  if (!review.value?.content) return '';
  return marked.parse(review.value.content);
})

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
  } catch (err) {
    error.value = err.response?.data?.error || 'Failed to generate review'
    console.error(err)
  } finally {
    loading.value = false
  }
}

// Reset review when PR changes
watch(() => props.pr, () => {
  review.value = null
  prDetails.value = null
  error.value = null
})
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
          <span>Reviewed by {{ review.model }}</span>
          <span>•</span>
          <span>{{ new Date(review.timestamp).toLocaleString() }}</span>
        </div>
        <div class="review-content" v-html="reviewHtml"></div>
      </div>

      <div v-else class="empty-state">
        <h3>Ready to Review</h3>
        <p>Click "Review with AI" to start the code review</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
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
  color: var(--primary-light);
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
  padding: 0.25rem 0.5rem;
  border-radius: 6px;
  font-family: 'Monaco', 'Menlo', 'Consolas', 'Courier New', monospace;
  font-size: 0.875em;
  color: var(--primary);
  border: 1px solid var(--border);
}

.review-content :deep(pre) {
  background: var(--bg-tertiary);
  padding: 1.5rem;
  border-radius: 12px;
  overflow-x: auto;
  margin: 1.5rem 0;
  border: 1px solid var(--border);
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
  padding: 1rem 1.5rem;
  margin: 1.5rem 0;
  background: var(--bg-tertiary);
  border-radius: 0 8px 8px 0;
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
  border-radius: 8px;
  overflow: hidden;
}

.review-content :deep(th) {
  background: var(--bg-hover);
  padding: 0.75rem 1rem;
  text-align: left;
  font-weight: 600;
  color: var(--text);
  border-bottom: 2px solid var(--border);
}

.review-content :deep(td) {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--border);
  color: var(--text-secondary);
}

.review-content :deep(tr:last-child td) {
  border-bottom: none;
}
</style>