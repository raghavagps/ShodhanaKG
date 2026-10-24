import { test, expect } from '@playwright/test';

test.describe('Chatbot Widget Flow', () => {
  test('should open, display welcome message, and allow sending messages', async ({ page }) => {
    // Go to homepage
    await page.goto('/');
    
    // Check if the chat button exists
    const chatButton = page.getByRole('button', { name: /open assistant/i });
    await expect(chatButton).toBeVisible();
    
    // Click to open
    await chatButton.click();
    
    // Check welcome message
    await expect(page.getByText(/Hello. I'm the ShodhanaKG Assistant/i)).toBeVisible();
    
    // Click a suggestion chip
    const suggestionBtn = page.getByRole('button', { name: /Compare Vamana and Virechana/i });
    if (await suggestionBtn.isVisible()) {
      await suggestionBtn.click();
      
      // User message should appear
      await expect(page.getByText('Compare Vamana and Virechana').last()).toBeVisible();
    }
  });

  test('should persist chat history after reload', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('button', { name: /open assistant/i }).click();
    
    // Wait for the panel to be visible before interacting
    const chatPanel = page.getByRole('complementary', { name: 'ShodhanaKG Assistant' });
    await expect(chatPanel).toBeVisible();

    const input = page.getByRole('textbox', { name: 'Question', exact: true });
    await input.fill('Hello persistent world');
    await input.press('Enter');
    
    await expect(page.getByText('Hello persistent world')).toBeVisible();
    
    // Reload page
    await page.reload();
    await page.getByRole('button', { name: /open assistant/i }).click();
    
    // Message should still be there
    await expect(page.getByText('Hello persistent world')).toBeVisible();
    
    // Clear chat
    await page.getByTitle('Start new conversation').click();
    await expect(page.getByText('Hello persistent world')).not.toBeVisible();
  });
});
