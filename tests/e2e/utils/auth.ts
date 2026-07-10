import type { Page } from "@playwright/test";

export const TOOL_USER_EMAIL = process.env.E2E_TEST_USER_EMAIL;
export const TOOL_USER_PASSWORD = process.env.E2E_TEST_USER_PASSWORD;

export function uniqueTestEmail() {
  return `e2e-${Date.now()}-${Math.floor(Math.random() * 1e6)}@e2e.docentesconcausa.test`;
}

export async function login(page: Page, email: string, password: string) {
  await page.goto("/login");
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.getByRole("button", { name: "Entrar" }).click();
  await page.waitForURL("/");
}
