<script setup>
import { ref, computed } from 'vue'
import { marked } from 'marked'

const props = defineProps({
  review: Object
})

const activeTab = ref('actionable')

const parseSections = computed(() => {
  if (!props.review?.content) return null

  const content = props.review.content
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

marked.setOptions({
  breaks: true,
  gfm: true
})
</script>

<template>
  <div class="review-content-wrapper">
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
    </div>
  </div>
</template>

<style scoped>
.review-content-wrapper {
  display: flex;
  flex-direction: column;
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
</style>
