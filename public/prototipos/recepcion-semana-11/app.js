(() => {
  const steps = ["step1", "step2", "step3", "step4", "successPanel", "criticalError"];
  const progress = [...document.querySelectorAll(".progress-step")];
  const $ = (id) => document.getElementById(id);
  let duplicateScenario = false;
  let savedDecision = "accept";

  function showPanel(id) {
    steps.forEach((step) => $(step).classList.toggle("hidden", step !== id));
    const activeStep = { step1: 1, step2: 2, step3: 3, step4: 4 }[id] || 0;
    progress.forEach((item) => {
      const number = Number(item.dataset.step);
      item.classList.toggle("is-current", number === activeStep);
      item.classList.toggle("is-complete", activeStep > 0 && number < activeStep);
      item.setAttribute("aria-current", number === activeStep ? "step" : "false");
    });
    window.scrollTo({ top: 0, behavior: "smooth" });
    const heading = $(id)?.querySelector("h2");
    if (heading) heading.setAttribute("tabindex", "-1"), heading.focus({ preventScroll: true });
  }

  function clearAlerts() {
    ["searchError", "verificationError", "decisionError"].forEach((id) => {
      $(id).classList.add("hidden");
      $(id).textContent = "";
    });
  }

  function beginDuplicateScenario() {
    duplicateScenario = true;
    $("sampleExam").textContent = "Hemograma completo";
    $("sampleContext").textContent = "Andrea R. · OI-74182 · Sangre total · Escenario de conflicto";
    $("specimenCode").value = "SP-2026-008472";
    $("orderSearch").value = "ORD-2026-0418";
    clearAlerts();
    showPanel("step2");
  }

  $("searchForm").addEventListener("submit", (event) => {
    event.preventDefault();
    clearAlerts();
    const term = $("orderSearch").value.trim().toUpperCase();
    if (!term || !["ORD-2026-0418", "SP-2026-008472"].includes(term)) {
      $("orderResult").classList.add("hidden");
      $("searchError").textContent = "No encontramos una coincidencia autorizada en esta demo. Revisa la referencia e intenta de nuevo; no se creó ninguna recepción.";
      $("searchError").classList.remove("hidden");
      $("searchError").focus();
      return;
    }
    duplicateScenario = false;
    $("orderResult").classList.remove("hidden");
  });

  $("selectSample").addEventListener("click", () => {
    duplicateScenario = false;
    $("specimenCode").value = "SP-2026-008472";
    clearAlerts();
    showPanel("step2");
  });
  $("duplicateScenario").addEventListener("click", beginDuplicateScenario);
  $("backToOrder").addEventListener("click", () => showPanel("step1"));
  $("cancelVerification").addEventListener("click", () => showPanel("step1"));
  $("backFromCritical").addEventListener("click", () => {
    duplicateScenario = false;
    $("specimenCode").value = "";
    showPanel("step2");
  });
  $("closeCritical").addEventListener("click", () => {
    duplicateScenario = false;
    $("specimenCode").value = "";
    $("orderSearch").focus();
    showPanel("step1");
    $("searchError").classList.remove("hidden");
    $("searchError").innerHTML = "<span class=\"alert-icon\" aria-hidden=\"true\">!</span><div><strong>Incidente registrado para revisión.</strong><p>El intento fue bloqueado; ninguna recepción ni evento de custodia fue creado.</p></div>";
  });

  $("toDecision").addEventListener("click", () => {
    clearAlerts();
    if (duplicateScenario || $("specimenCode").value.trim() === "SP-2026-008472-DUP") {
      showPanel("criticalError");
      return;
    }
    const checks = ["labelMatch", "containerMatch", "integrityOk", "sealOk"];
    const hasConfirmedException = checks.some((id) => !$(id).checked);
    $("pendingDecision").classList.toggle("hidden", !hasConfirmedException);
    const acceptOption = document.querySelector('input[name="decision"][value="accept"]');
    const rejectOption = document.querySelector('input[name="decision"][value="reject"]');
    acceptOption.disabled = hasConfirmedException;
    if (hasConfirmedException) {
      rejectOption.checked = true;
      rejectOption.dispatchEvent(new Event("change", { bubbles: true }));
    } else {
      acceptOption.checked = true;
      acceptOption.dispatchEvent(new Event("change", { bubbles: true }));
    }
    showPanel("step3");
  });

  $("backToVerification").addEventListener("click", () => showPanel("step2"));
  $("backToDecision").addEventListener("click", () => showPanel("step3"));
  document.querySelectorAll('input[name="decision"]').forEach((radio) => {
    radio.addEventListener("change", () => {
      const reject = radio.value === "reject" && radio.checked;
      if (!reject && radio.checked) return;
      document.querySelectorAll(".decision-option").forEach((option) => option.classList.toggle("selected", option.contains(radio) && radio.checked || option.querySelector('input[name="decision"]:checked') !== null));
      $("rejectionFields").classList.toggle("hidden", !reject);
      $("decisionSummary").classList.toggle("is-reject", reject);
      $("decisionSummary").querySelector(".decision-check").textContent = reject ? "!" : "✓";
      $("decisionSummary").querySelector(".status").textContent = reject ? "PROPUESTA: RECHAZAR" : "PROPUESTA: ACEPTAR";
      $("decisionSummary").querySelector(".status").className = reject ? "status status-red" : "status status-green";
      $("decisionSummary").querySelector("h3").textContent = reject ? "Se requiere motivo de rechazo" : "La muestra cumple las verificaciones";
      $("decisionSummary").querySelector("p").textContent = reject ? "Confirma una no conformidad y selecciona su causa." : "Etiqueta, recipiente, integridad y sello coinciden con la orden.";
    });
  });

  $("toCustody").addEventListener("click", () => {
    clearAlerts();
    const checked = document.querySelector('input[name="decision"]:checked');
    savedDecision = checked?.value || "accept";
    if (savedDecision === "reject" && !$("rejectReason").value) {
      $("decisionError").textContent = "Selecciona un motivo confirmado para rechazar la muestra.";
      $("decisionError").classList.remove("hidden");
      $("decisionError").focus();
      $("rejectReason").focus();
      return;
    }
    showPanel("step4");
  });

  $("saveReception").addEventListener("click", () => {
    const location = $("location").value;
    const reason = $("rejectReason").value;
    $("successStatus").textContent = savedDecision === "accept" ? "RECEPCIÓN ACEPTADA" : "RECEPCIÓN RECHAZADA";
    $("successStatus").className = savedDecision === "accept" ? "status status-green" : "status status-red";
    $("successTitle").textContent = savedDecision === "accept" ? "La muestra quedó registrada" : "El rechazo quedó registrado";
    $("successMessage").textContent = savedDecision === "accept"
      ? "La decisión y el primer evento de custodia se guardaron correctamente."
      : `La decisión y el primer evento de custodia se guardaron. Motivo: ${reason}. La muestra no pasa a procesamiento.`;
    $("timelineTitle").textContent = savedDecision === "accept" ? "Recepción aceptada" : "Recepción rechazada";
    $("successCode").textContent = $("specimenCode").value.trim() || "SP-2026-008472";
    $("successPanel").querySelector(".timeline-event .status").textContent = "REGISTRADO";
    showPanel("successPanel");
  });

  $("copyCode").addEventListener("click", async () => {
    const code = $("successCode").textContent;
    try {
      await navigator.clipboard.writeText(code);
      $("copyCode").textContent = "Copiado";
    } catch {
      $("copyCode").textContent = code;
    }
  });

  $("newReception").addEventListener("click", reset);
  $("restartButton").addEventListener("click", reset);

  function reset() {
    duplicateScenario = false;
    savedDecision = "accept";
    $("searchForm").reset();
    $("orderResult").classList.remove("hidden");
    $("orderSearch").value = "ORD-2026-0418";
    $("specimenCode").value = "SP-2026-008472";
    $("observation").value = "";
    $("rejectObservation").value = "";
    $("rejectReason").value = "";
    ["labelMatch", "containerMatch", "integrityOk", "sealOk"].forEach((id) => { $(id).checked = true; });
    document.querySelector('input[name="decision"][value="accept"]').checked = true;
    document.querySelector('input[name="decision"][value="accept"]').disabled = false;
    $("pendingDecision").classList.add("hidden");
    $("rejectionFields").classList.add("hidden");
    $("decisionSummary").classList.remove("is-reject");
    $("decisionSummary").querySelector(".decision-check").textContent = "✓";
    $("decisionSummary").querySelector(".status").textContent = "PROPUESTA: ACEPTAR";
    $("decisionSummary").querySelector(".status").className = "status status-green";
    $("decisionSummary").querySelector("h3").textContent = "La muestra cumple las verificaciones";
    $("decisionSummary").querySelector("p").textContent = "Etiqueta, recipiente, integridad y sello coinciden con la orden.";
    $("copyCode").textContent = "Copiar";
    clearAlerts();
    showPanel("step1");
  }

  const demoState = new URLSearchParams(window.location.search).get("demo");
  if (demoState === "critical") {
    duplicateScenario = true;
    showPanel("criticalError");
  } else if (demoState === "happy") {
    $("successCode").textContent = "SP-2026-008472";
    showPanel("successPanel");
  } else if (demoState === "rejection") {
    savedDecision = "reject";
    $("successStatus").textContent = "RECEPCIÓN RECHAZADA";
    $("successStatus").className = "status status-red";
    $("successTitle").textContent = "El rechazo quedó registrado";
    $("successMessage").textContent = "La decisión y el primer evento de custodia se guardaron. Motivo: Sello no íntegro. La muestra no pasa a procesamiento.";
    $("timelineTitle").textContent = "Recepción rechazada";
    $("successCode").textContent = "SP-2026-008472";
    showPanel("successPanel");
  }
})();
