import { defineConfig } from '@playwright/test';
import config from './playwright.config';

// Use the user's running servers without starting or stopping either service.
export default defineConfig({ ...config, webServer: undefined });
