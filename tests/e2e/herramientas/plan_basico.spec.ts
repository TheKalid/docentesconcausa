import { test, expect, type FrameLocator, type Locator } from "@playwright/test";
import { login, TOOL_USER_EMAIL, TOOL_USER_PASSWORD } from "../utils/auth";

const IFRAME_TITLE = "Generador de Planeaciones";

const MOCK_PLAN_RESPONSE = {
  status: "completo",
  success: true,
  usos_restantes: 7,
  plan: {
    datos_principales: {
      proyecto: "Proyecto E2E de prueba",
      metodologia: "Aprendizaje Basado en Proyectos (ABP)",
      pda: "PDA de prueba generado por el mock",
    },
    lista_materiales: ["Cuaderno", "Colores", "Tijeras"],
    planeacion_completa:
      "## Sesión 1\nActividad de prueba con **contenido en markdown**.",
    sugerencias_didacticas: ["Sugerencia didáctica uno", "Sugerencia didáctica dos"],
    aviso: "Revisa y adapta esta planeación a las necesidades de tu grupo.",
  },
};

const MOCK_ERROR_RESPONSE = {
  status: "error",
  success: false,
  error: "No se pudo generar la planeación. Intenta de nuevo.",
};

async function waitForOptions(select: Locator, minCount = 2) {
  await expect(async () => {
    const count = await select.locator("option").count();
    expect(count).toBeGreaterThanOrEqual(minCount);
  }).toPass({ timeout: 10_000 });
}

async function selectFirstRealOption(select: Locator) {
  await waitForOptions(select);
  const value = await select.locator("option").nth(1).getAttribute("value");
  await select.selectOption(value!);
}

async function fillValidPrimariaForm(frame: FrameLocator) {
  await frame.locator("#grado").selectOption({ label: "1º de Primaria" });
  await selectFirstRealOption(frame.locator("#campoFormativo"));
  await selectFirstRealOption(frame.locator("#contenido"));
  await selectFirstRealOption(frame.locator("#pda"));
  await frame.locator("#ejeArticulador").selectOption("Inclusión");
  await frame.locator("#tiempo").selectOption("5 días");
}

test.describe("Herramienta: Generador de Planeaciones (plan-básico)", () => {
  test.beforeEach(async ({ page }) => {
    test.skip(
      !TOOL_USER_EMAIL || !TOOL_USER_PASSWORD,
      "Requiere E2E_TEST_USER_EMAIL y E2E_TEST_USER_PASSWORD de un usuario con plan activo y créditos disponibles."
    );

    await login(page, TOOL_USER_EMAIL!, TOOL_USER_PASSWORD!);
    await page.goto("/herramientas/plan-basico");
  });

  test("el selector de grado agrupa las opciones por nivel educativo", async ({
    page,
  }) => {
    const frame = page.frameLocator(`iframe[title="${IFRAME_TITLE}"]`);
    const grado = frame.locator("#grado");

    await expect(grado.locator("optgroup")).toHaveCount(3);
    await expect(grado.locator("optgroup[label='Preescolar'] option")).toHaveCount(3);
    await expect(grado.locator("optgroup[label='Primaria'] option")).toHaveCount(6);
    await expect(grado.locator("optgroup[label='Secundaria'] option")).toHaveCount(3);
  });

  test("la selección en cascada (grado > campo formativo > contenido > PDA) funciona para Primaria", async ({
    page,
  }) => {
    const frame = page.frameLocator(`iframe[title="${IFRAME_TITLE}"]`);

    await expect(frame.locator("#contenedorAsignatura")).toBeHidden();

    await fillValidPrimariaForm(frame);

    await expect(frame.locator("#campoFormativo")).not.toHaveValue("");
    await expect(frame.locator("#contenido")).not.toHaveValue("");
    await expect(frame.locator("#pda")).not.toHaveValue("");
  });

  test("al elegir un grado de Secundaria aparece el selector de Asignatura y filtra los campos formativos", async ({
    page,
  }) => {
    const frame = page.frameLocator(`iframe[title="${IFRAME_TITLE}"]`);

    await frame.locator("#grado").selectOption({ label: "1º de Secundaria" });

    const contenedorAsignatura = frame.locator("#contenedorAsignatura");
    await expect(contenedorAsignatura).toBeVisible();

    const asignatura = frame.locator("#asignatura");
    await selectFirstRealOption(asignatura);

    const campoFormativo = frame.locator("#campoFormativo");
    await waitForOptions(campoFormativo, 2);
    await selectFirstRealOption(campoFormativo);

    await selectFirstRealOption(frame.locator("#contenido"));
    await selectFirstRealOption(frame.locator("#pda"));

    await expect(frame.locator("#pda")).not.toHaveValue("");
  });

  test("genera y muestra el resultado devuelto por el servidor", async ({
    page,
  }) => {
    await page.route("**/api/tools/plan-basico", async (route) => {
      await route.fulfill({ json: MOCK_PLAN_RESPONSE });
    });

    const frame = page.frameLocator(`iframe[title="${IFRAME_TITLE}"]`);
    await fillValidPrimariaForm(frame);
    await frame.getByRole("button", { name: "Generar Planeación" }).click();

    const resultado = frame.locator("#resultadoPlaneacion");
    await expect(resultado.locator("h3")).toHaveText("Proyecto E2E de prueba");
    await expect(resultado).toContainText("Aprendizaje Basado en Proyectos (ABP)");
    await expect(resultado).toContainText("PDA de prueba generado por el mock");

    await expect(resultado.locator("li")).toHaveCount(
      MOCK_PLAN_RESPONSE.plan.lista_materiales.length +
        MOCK_PLAN_RESPONSE.plan.sugerencias_didacticas.length
    );
    await expect(resultado).toContainText("Cuaderno");
    await expect(resultado).toContainText("Sugerencia didáctica uno");
    await expect(resultado).toContainText("Sesión 1");
    await expect(resultado).toContainText(
      "Revisa y adapta esta planeación a las necesidades de tu grupo."
    );

    await expect(frame.locator("#seccionResultadoContenedor")).toBeVisible();
    await expect(frame.locator("#contenedorBotones")).toBeVisible();
    await expect(frame.locator("#contador-usos")).toHaveText(
      String(MOCK_PLAN_RESPONSE.usos_restantes)
    );
  });

  test("muestra un aviso de error y conserva los créditos cuando el servidor falla", async ({
    page,
  }) => {
    await page.route("**/api/tools/plan-basico", async (route) => {
      await route.fulfill({ json: MOCK_ERROR_RESPONSE, status: 200 });
    });

    const frame = page.frameLocator(`iframe[title="${IFRAME_TITLE}"]`);
    await fillValidPrimariaForm(frame);
    await frame.getByRole("button", { name: "Generar Planeación" }).click();

    const resultado = frame.locator("#resultadoPlaneacion");
    await expect(resultado).toContainText(MOCK_ERROR_RESPONSE.error);
    await expect(resultado).toContainText("Sus créditos están a salvo");
  });
});
