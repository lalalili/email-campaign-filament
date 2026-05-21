import { createApp } from 'vue';
import EmailTemplateBuilderApp from './EmailTemplateBuilderApp.vue';

function mountEmailTemplateBuilder(el: HTMLElement) {
  const dataset = el.dataset;
  createApp(EmailTemplateBuilderApp, {
    campaignId: Number(dataset.campaignId),
    saveEndpoint: dataset.saveEndpoint ?? '',
    csrfToken: dataset.csrfToken ?? '',
    availableVariables: dataset.availableVariables
      ? JSON.parse(dataset.availableVariables)
      : undefined,
  }).mount(el);
}

document.querySelectorAll<HTMLElement>('[data-email-template-builder]').forEach(mountEmailTemplateBuilder);
