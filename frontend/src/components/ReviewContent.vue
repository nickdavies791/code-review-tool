<script setup>
import { ref, computed } from 'vue'
import { marked } from 'marked'

const props = defineProps({
  review: Object
})

const expandedSections = ref({
  actionable: true,
  testScenarios: true
})

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

const toggleSection = (section) => {
  expandedSections.value[section] = !expandedSections.value[section]
}

marked.setOptions({
  breaks: true,
  gfm: true
})
</script>

<template>
  <div class="review-content-wrapper">
    <!-- Review Metadata -->
    <div class="review-info">
      <div class="review-badge">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 11l3 3L22 4"></path>
          <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
        </svg>
        AI Review Complete
      </div>
      <div class="review-timestamp">{{ new Date(review.timestamp).toLocaleString() }}</div>
    </div>

    <!-- Actionable Items Section -->
    <div class="review-section" v-if="actionableHtml">
      <div class="section-header" @click="toggleSection('actionable')">
        <div class="section-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <h3>Action Items</h3>
        </div>
        <svg
          class="chevron"
          :class="{ expanded: expandedSections.actionable }"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
          <polyline points="6 9 12 15 18 9"></polyline>
        </svg>
      </div>
      <Transition name="expand">
        <div v-if="expandedSections.actionable" class="section-content">
          <div class="review-content" v-html="actionableHtml"></div>
        </div>
      </Transition>
    </div>

    <!-- Test Scenarios Section -->
    <div class="review-section" v-if="testScenariosHtml">
      <div class="section-header" @click="toggleSection('testScenarios')">
        <div class="section-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
          </svg>
          <h3>Test Scenarios</h3>
        </div>
        <svg
          class="chevron"
          :class="{ expanded: expandedSections.testScenarios }"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
          <polyline points="6 9 12 15 18 9"></polyline>
        </svg>
      </div>
      <Transition name="expand">
        <div v-if="expandedSections.testScenarios" class="section-content">
          <div class="review-content" v-html="testScenariosHtml"></div>
        </div>
      </Transition>
    </div>
  </div>
</template>

<style scoped>
.review-content-wrapper {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.review-info {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.5rem;
  background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(16, 185, 129, 0.05));
  border-radius: 12px;
  border: 1px solid var(--border);
}

.review-badge {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--primary);
}

.review-badge svg {
  color: var(--secondary);
}

.review-timestamp {
  font-size: 0.8rem;
  color: var(--text-muted);
  font-weight: 500;
}

.review-section {
  background: var(--bg-card);
  border: 2px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
  transition: all 0.2s;
}

.review-section:hover {
  border-color: var(--primary-light);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.section-header {
  padding: 1.25rem 1.5rem;
  background: var(--bg-hover);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: space-between;
  transition: all 0.2s;
  border-bottom: 1px solid var(--border);
}

.section-header:hover {
  background: var(--bg-elevated);
}

.section-title {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.section-title svg {
  color: var(--primary);
  flex-shrink: 0;
}

.section-title h3 {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--text);
}

.chevron {
  color: var(--text-muted);
  transition: transform 0.3s ease;
  flex-shrink: 0;
}

.chevron.expanded {
  transform: rotate(180deg);
}

.section-content {
  padding: 2rem;
}

.expand-enter-active,
.expand-leave-active {
  transition: all 0.3s ease;
  overflow: hidden;
}

.expand-enter-from,
.expand-leave-to {
  opacity: 0;
  max-height: 0;
}

.expand-enter-to,
.expand-leave-from {
  opacity: 1;
  max-height: 10000px;
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
