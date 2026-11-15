import { createApp } from 'vue';
import type { WidgetFrameConfig } from '@/types/widget';
import WidgetApp from './WidgetApp.vue';

// The widget page is a small standalone Vue app, not an Inertia page:
// it runs inside an iframe on other websites, without login or the app layout.
const element = document.getElementById('widget');

if (element?.dataset.config) {
    const config = JSON.parse(element.dataset.config) as WidgetFrameConfig;

    createApp(WidgetApp, { config }).mount(element);
}
