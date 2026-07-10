import { test, expect } from '@playwright/test';

test('la página principal carga y muestra el Hero', async ({ page }) => {
  await page.goto('/');

  await expect(
    page.getByRole('heading', {
      name: 'Herramientas para docentes que planean con propósito',
    }),
  ).toBeVisible();
});
