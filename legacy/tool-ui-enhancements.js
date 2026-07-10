(function () {
  const GENERATE_BUTTON_SELECTORS = [
    "#btnGenerar",
    "#btnGenerarIA",
    "#btn-generar",
    "#btn-consultar",
    "#btn-analizar",
    "#btn-protocolo",
  ].join(", ");

  const SUBMIT_BUTTON_SELECTORS = [
    "#submitBtn",
    "#btn-submit",
    ".submit-button",
    ".btn-submit",
  ].join(", ");

  let loadingCount = 0;

  function esVisible(elemento) {
    if (!elemento || elemento.disabled) return false;

    let nodo = elemento;
    while (nodo && nodo !== document.body) {
      const estilo = window.getComputedStyle(nodo);
      if (estilo.display === "none" || estilo.visibility === "hidden") {
        return false;
      }
      nodo = nodo.parentElement;
    }

    return true;
  }

  function obtenerEtiqueta(campo) {
    if (campo.id) {
      const etiqueta = document.querySelector(`label[for="${campo.id}"]`);
      if (etiqueta) {
        return etiqueta.textContent
          .replace(/^\d+\.\s*/, "")
          .replace(/:$/, "")
          .trim();
      }
    }

    const etiquetaPadre = campo.closest("label");
    if (etiquetaPadre) {
      return etiquetaPadre.textContent.trim();
    }

    return campo.name || campo.id || "Campo requerido";
  }

  function limpiarErroresValidacion(formulario) {
    formulario.querySelectorAll(".campo-error").forEach((campo) => {
      campo.classList.remove("campo-error");
    });

    formulario.querySelectorAll(".aviso-validacion").forEach((aviso) => {
      aviso.style.display = "none";
      aviso.innerHTML = "";
    });

    const avisoGlobal = document.getElementById("aviso-validacion-global");
    if (avisoGlobal) {
      avisoGlobal.style.display = "none";
      avisoGlobal.innerHTML = "";
    }
  }

  function obtenerAvisoValidacion(formulario, disparador) {
    let aviso = formulario.querySelector(".aviso-validacion");
    if (aviso) return aviso;

    aviso = document.getElementById("aviso-validacion-global");
    if (!aviso) {
      aviso = document.createElement("div");
      aviso.id = "aviso-validacion-global";
      aviso.className = "aviso-validacion";
      aviso.setAttribute("role", "alert");
    }

    const referencia =
      disparador ||
      formulario.querySelector(SUBMIT_BUTTON_SELECTORS) ||
      formulario.querySelector(GENERATE_BUTTON_SELECTORS);

    if (referencia && referencia.parentElement) {
      referencia.parentElement.insertBefore(aviso, referencia);
    } else {
      formulario.appendChild(aviso);
    }

    return aviso;
  }

  function mostrarErroresValidacion(formulario, faltantes, disparador) {
    const aviso = obtenerAvisoValidacion(formulario, disparador);
    aviso.innerHTML = `
      <strong>Completa las siguientes opciones antes de continuar:</strong>
      <ul>${faltantes.map((campo) => `<li>${campo}</li>`).join("")}</ul>
    `;
    aviso.style.display = "block";

    const primerCampoInvalido = formulario.querySelector(".campo-error");
    if (primerCampoInvalido) {
      primerCampoInvalido.scrollIntoView({ behavior: "smooth", block: "center" });
      if (typeof primerCampoInvalido.focus === "function") {
        primerCampoInvalido.focus({ preventScroll: true });
      }
    }
  }

  function campoEstaVacio(campo) {
    if (campo.type === "checkbox" || campo.type === "radio") {
      return false;
    }

    if (campo.tagName === "SELECT" && campo.multiple) {
      return campo.selectedOptions.length === 0;
    }

    if (campo.type === "file") {
      return !campo.files || campo.files.length === 0;
    }

    return !String(campo.value || "").trim();
  }

  function validarGruposCheckbox(formulario, faltantes) {
    formulario.querySelectorAll(".checkbox-group").forEach((grupo) => {
      if (grupo.closest("[data-optional='true']")) return;
      if (!esVisible(grupo)) return;

      const checkboxes = grupo.querySelectorAll('input[type="checkbox"]');
      if (checkboxes.length === 0) return;

      const algunoMarcado = Array.from(checkboxes).some((checkbox) => checkbox.checked);
      if (algunoMarcado) return;

      const etiquetaGrupo = grupo.previousElementSibling;
      const nombre =
        etiquetaGrupo && etiquetaGrupo.tagName === "LABEL"
          ? etiquetaGrupo.textContent.replace(/^\d+\.\s*/, "").replace(/:$/, "").trim()
          : "Opciones requeridas";

      faltantes.push(nombre);
      checkboxes[0].classList.add("campo-error");
    });
  }

  function validarFormularioHerramienta(disparador) {
    const formulario =
      (disparador && disparador.closest && disparador.closest("form")) ||
      document.querySelector(
        ".form-section form, .form-container form, main form, .container form, form"
      );

    if (!formulario) return true;

    limpiarErroresValidacion(formulario);

    const faltantes = [];
    const campos = formulario.querySelectorAll("input, select, textarea");

    campos.forEach((campo) => {
      if (campo.type === "hidden" || campo.type === "button" || campo.type === "submit") {
        return;
      }
      if (campo.dataset.optional === "true") return;
      if (!esVisible(campo)) return;

      const esRequerido =
        campo.required ||
        campo.hasAttribute("required") ||
        (campo.tagName === "SELECT" &&
          !campo.disabled &&
          campo.dataset.optional !== "true");

      if (!esRequerido) return;

      if (campoEstaVacio(campo)) {
        faltantes.push(obtenerEtiqueta(campo));
        campo.classList.add("campo-error");
      }
    });

    validarGruposCheckbox(formulario, faltantes);

    if (faltantes.length === 0) return true;

    mostrarErroresValidacion(formulario, faltantes, disparador);
    return false;
  }

  function mostrarModalGenerando() {
    const modal = document.getElementById("modalGenerando");
    if (!modal) return;
    modal.classList.add("activo");
    modal.setAttribute("aria-hidden", "false");
  }

  function ocultarModalGenerando() {
    const modal = document.getElementById("modalGenerando");
    if (!modal) return;
    modal.classList.remove("activo");
    modal.setAttribute("aria-hidden", "true");
  }

  function esBotonGeneracion(elemento) {
    if (!elemento) return false;
    if (elemento.matches && elemento.matches(GENERATE_BUTTON_SELECTORS)) return true;
    if (elemento.matches && elemento.matches(SUBMIT_BUTTON_SELECTORS)) return true;
    return false;
  }

  function esEnvioGeneracion(formulario, submitter) {
    if (!submitter) {
      return Boolean(formulario.querySelector(SUBMIT_BUTTON_SELECTORS));
    }
    return esBotonGeneracion(submitter);
  }

  document.addEventListener(
    "click",
    function (evento) {
      const boton = evento.target.closest(GENERATE_BUTTON_SELECTORS);
      if (!boton || boton.disabled) return;
      if (!validarFormularioHerramienta(boton)) {
        evento.preventDefault();
        evento.stopImmediatePropagation();
      }
    },
    true
  );

  document.addEventListener(
    "submit",
    function (evento) {
      const formulario = evento.target;
      if (!(formulario instanceof HTMLFormElement)) return;
      if (!esEnvioGeneracion(formulario, evento.submitter)) return;
      if (!validarFormularioHerramienta(evento.submitter || formulario)) {
        evento.preventDefault();
        evento.stopImmediatePropagation();
      }
    },
    true
  );

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("form").forEach((formulario) => {
      formulario.addEventListener("change", function (evento) {
        const campo = evento.target;
        if (!(campo instanceof HTMLElement)) return;
        campo.classList.remove("campo-error");
        if (!formulario.querySelector(".campo-error")) {
          limpiarErroresValidacion(formulario);
        }
      });
    });
  });

  const nativeFetch = window.fetch.bind(window);
  window.fetch = function (input, init) {
    const opts = init ? { ...init } : {};
    if (!opts.credentials) opts.credentials = "same-origin";

    const url = typeof input === "string" ? input : input?.url || "";
    const metodo = (opts.method || "GET").toUpperCase();
    const esApiHerramienta = url.includes("/api/tools/") && metodo === "POST";

    if (esApiHerramienta) {
      loadingCount += 1;
      mostrarModalGenerando();
    }

    return nativeFetch(input, opts).finally(function () {
      if (esApiHerramienta) {
        loadingCount = Math.max(0, loadingCount - 1);
        if (loadingCount === 0) {
          ocultarModalGenerando();
        }
      }
    });
  };

  window.__LEGACY_TOOL_UI__ = {
    validarFormularioHerramienta: validarFormularioHerramienta,
    mostrarModalGenerando: mostrarModalGenerando,
    ocultarModalGenerando: ocultarModalGenerando,
  };
})();
