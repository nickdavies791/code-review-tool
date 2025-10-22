<script setup>
import { ref } from 'vue'
import { marked } from 'marked'

const props = defineProps({
  prDetails: Object
})

const prInfoTab = ref('overview')

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

marked.setOptions({
  breaks: true,
  gfm: true
})
</script>

<template>
  <div class="pr-details-section">
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
</template>

<style scoped>
.pr-details-section {
  margin-bottom: 2rem;
  background: var(--bg-card);
  border: 2px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.pr-details-tabs {
  display: flex;
  gap: 0.25rem;
  background: var(--bg-hover);
  border-bottom: 2px solid var(--border);
  padding: 0 1rem;
}

.pr-tab {
  padding: 0.75rem 1.25rem;
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  color: var(--text-muted);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
  bottom: -2px;
  border-radius: 6px 6px 0 0;
}

.pr-tab:hover {
  color: var(--text);
  background: rgba(99, 102, 241, 0.08);
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
</style>
