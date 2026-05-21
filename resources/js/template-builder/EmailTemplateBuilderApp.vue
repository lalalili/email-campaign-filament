<script setup lang="ts">
import { ref } from 'vue';
import { BuilderShell } from '@builder-ui-core';

const props = defineProps<{
  campaignId: number;
  saveEndpoint: string;
  csrfToken: string;
  availableVariables?: Array<{ key: string; label: string; example: string }>;
}>();

const subject = ref('');
const htmlTemplate = ref('');
const isSaving = ref(false);
const isDirty = ref(false);
const saveError = ref(false);

const saveStatus = ref<'saved' | 'unsaved' | 'saving' | 'error'>('saved');

function markDirty() {
  isDirty.value = true;
  saveStatus.value = 'unsaved';
}

async function save() {
  isSaving.value = true;
  saveStatus.value = 'saving';
  try {
    const response = await fetch(props.saveEndpoint, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': props.csrfToken },
      body: JSON.stringify({ subject: subject.value, html_template: htmlTemplate.value }),
    });
    if (!response.ok) throw new Error('Save failed');
    isDirty.value = false;
    saveStatus.value = 'saved';
  } catch {
    saveError.value = true;
    saveStatus.value = 'error';
  } finally {
    isSaving.value = false;
  }
}

function varTag(key: string) {
  return '{{ ' + key + ' }}';
}

function insertVariable(key: string) {
  htmlTemplate.value += `{{ ${key} }}`;
  markDirty();
}

const emptyPreview = '<p style="color:#aaa">預覽將在此顯示</p>';

const saveStatusLabel = {
  saved: '已儲存',
  unsaved: '未儲存',
  saving: '儲存中…',
  error: '儲存失敗',
};
</script>

<template>
  <BuilderShell>
    <template #topbar>
      <div class="sb-topbar-left">
        <div class="sb-logo-spacer"></div>
        <input
          v-model="subject"
          class="sb-title-input"
          placeholder="郵件主旨…"
          @input="markDirty"
        />
      </div>

      <div class="sb-topbar-spacer" />

      <div class="sb-topbar-right">
        <span
          class="sb-save-status"
          :class="{ saving: saveStatus === 'saving', error: saveStatus === 'error' }"
        >
          <span class="sb-save-dot" />
          {{ saveStatusLabel[saveStatus] }}
        </span>
        <button
          type="button"
          class="sb-btn accent"
          :disabled="isSaving || !isDirty"
          @click="save"
        >
          儲存範本
        </button>
      </div>
    </template>

    <!-- ── Template editor body ── -->
    <div class="etb-body">
      <main class="etb-canvas">
        <div class="etb-canvas-inner">
          <label class="etb-field-label">HTML 郵件內容</label>
          <textarea
            v-model="htmlTemplate"
            class="etb-html-editor"
            placeholder="在此輸入 HTML 郵件內容，或使用右側變數面板插入動態欄位…"
            spellcheck="false"
            @input="markDirty"
          />
          <div class="etb-preview-wrap">
            <label class="etb-field-label">預覽</label>
            <div class="etb-preview" v-html="htmlTemplate || emptyPreview" />
          </div>
        </div>
      </main>

      <aside class="etb-variables-panel">
        <div class="etb-panel-header">可用變數</div>
        <div class="etb-variables-list">
          <button
            v-for="v in (availableVariables ?? [])"
            :key="v.key"
            type="button"
            class="etb-variable-btn"
            :title="`範例：${v.example}`"
            @click="insertVariable(v.key)"
          >
            <span class="etb-variable-key">{{ varTag(v.key) }}</span>
            <span class="etb-variable-label">{{ v.label }}</span>
          </button>
          <p v-if="!availableVariables?.length" class="etb-variables-empty">
            此活動類型無可用變數
          </p>
        </div>
      </aside>
    </div>
  </BuilderShell>
</template>

<style>
/* ── Email Template Builder layout ── */
.etb-body {
  display: grid;
  grid-template-columns: 1fr 280px;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

.etb-canvas {
  background: var(--c-bg);
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}

.etb-canvas-inner {
  max-width: 860px;
  margin: 0 auto;
  padding: 20px 28px 60px;
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.etb-field-label {
  display: block;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--c-ink3);
  margin-bottom: 6px;
}

.etb-html-editor {
  width: 100%;
  min-height: 280px;
  padding: 12px;
  border: 1.5px solid var(--c-line2);
  border-radius: var(--r2);
  background: var(--c-surface);
  font-family: var(--mono);
  font-size: 13px;
  color: var(--c-ink);
  resize: vertical;
  outline: none;
  box-sizing: border-box;
  line-height: 1.6;
  transition: border-color 150ms;
}
.etb-html-editor:focus { border-color: var(--c-accent); }

.etb-preview-wrap { border-top: 1px solid var(--c-line); padding-top: 16px; }

.etb-preview {
  background: var(--c-surface);
  border: 1.5px solid var(--c-line2);
  border-radius: var(--r2);
  padding: 20px 24px;
  min-height: 120px;
  font-size: 14px;
  line-height: 1.6;
  overflow: auto;
}

/* ── Variables panel ── */
.etb-variables-panel {
  background: var(--c-surface);
  border-left: 1px solid var(--c-line);
  display: flex;
  flex-direction: column;
  overflow-y: auto;
}

.etb-panel-header {
  padding: 12px 14px 8px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--c-ink3);
  border-bottom: 1px solid var(--c-line);
  flex-shrink: 0;
}

.etb-variables-list {
  padding: 8px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.etb-variable-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 7px 10px;
  border-radius: var(--r1);
  background: none;
  border: none;
  cursor: pointer;
  text-align: left;
  transition: background 120ms;
  width: 100%;
}
.etb-variable-btn:hover { background: var(--c-s2); }

.etb-variable-key {
  font-family: var(--mono);
  font-size: 11px;
  color: var(--c-accent);
  background: var(--c-accent2);
  padding: 1px 5px;
  border-radius: 3px;
  flex-shrink: 0;
}

.etb-variable-label {
  font-size: 12px;
  color: var(--c-ink2);
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.etb-variables-empty {
  font-size: 12px;
  color: var(--c-ink4);
  text-align: center;
  padding: 20px 0;
  margin: 0;
}
</style>
