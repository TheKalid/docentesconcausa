import { test, expect } from "@playwright/test";
import { login, TOOL_USER_EMAIL, TOOL_USER_PASSWORD } from "../utils/auth";

test.describe("Login", () => {
  test("inicia sesión con credenciales válidas y redirige al inicio", async ({
    page,
  }) => {
    test.skip(
      !TOOL_USER_EMAIL || !TOOL_USER_PASSWORD,
      "Requiere E2E_TEST_USER_EMAIL y E2E_TEST_USER_PASSWORD."
    );

    await login(page, TOOL_USER_EMAIL!, TOOL_USER_PASSWORD!);

    await expect(page).toHaveURL("/");
  });

  test("muestra un error con credenciales inválidas", async ({ page }) => {
    await page.goto("/login");
    await page.locator('input[name="email"]').fill("usuario-inexistente@e2e.test");
    await page.locator('input[name="password"]').fill("contraseñaIncorrecta123");
    await page.getByRole("button", { name: "Entrar" }).click();

    await expect(
      page.getByText("Correo o contraseña incorrectos.")
    ).toBeVisible();
    await expect(page).toHaveURL(/\/login/);
  });

  test("exige correo y contraseña antes de enviar el formulario", async ({
    page,
  }) => {
    await page.goto("/login");
    await page.getByRole("button", { name: "Entrar" }).click();

    const emailValid = await page
      .locator('input[name="email"]')
      .evaluate((el: HTMLInputElement) => el.checkValidity());
    expect(emailValid).toBe(false);
    await expect(page).toHaveURL(/\/login/);
  });

  test("enlaza a la página de registro", async ({ page }) => {
    await page.goto("/login");
    // El CTA tiene una animación de pulso (transform: scale) permanente,
    // por lo que nunca queda "estable" para el chequeo de accionabilidad.
    await page
      .getByRole("link", { name: "Regístrate aquí" })
      .click({ force: true });

    await expect(page).toHaveURL(/\/registro/);
  });
});
