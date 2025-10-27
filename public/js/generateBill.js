// js/generateBill.js
$(document).ready(function () {
  const token = localStorage.getItem('token');
  if (!token) { window.location.href = 'index.html'; return; }

  // appointment ID from URL
  const urlParams = new URLSearchParams(window.location.search);
  const appointmentId = urlParams.get('appointmentId');
  if (!appointmentId) {
    alert('Missing appointment ID');
    window.location.href = 'appointments.html';
    return;
  }
  $('#appointmentBadge').text(appointmentId);

  // hospital id: prefer selectedHospitalId in localStorage (set elsewhere in your app)
  const hospital_id = localStorage.getItem('selectedHospitalId') || localStorage.getItem('hospital_id') || null;

  const API_BASE = 'http://localhost:8080/billing';
  const $consultationPrice = $('#consultationPrice');
  const $consultationName = $('#consultationName');
  const $sumConsultation = $('#sumConsultation');
  const $serviceSelect = $('#serviceSelect');
  const $selectedServices = $('#selectedServices');
  const $servicesSummaryWrap = $('#servicesSummaryWrap');
  const $totalAmount = $('#totalAmount');
  const $statusHint = $('#statusHint');

  // local map of serviceId -> { service_id, unit_price, serviceName, serviceType }
  let availableServices = {};
  // list of selected service IDs (unique)
  let selectedIds = new Set();
  let consultation = { service_id: null, service_name: 'Consultation', unit_price: 0.00 };

  // Helpers
  function formatMoney(n) {
    return '₹ ' + parseFloat(n || 0).toFixed(2);
  }

  function renderSelectedChips() {
    $selectedServices.empty();
    $servicesSummaryWrap.empty();
    let sumServices = 0;

    selectedIds.forEach(id => {
      const s = availableServices[id];
      if (!s) return;
      sumServices += parseFloat(s.unit_price || 0);
      // Chip
      const $chip = $(`<span class="chip" data-id="${id}">
        <span>${s.serviceName} · ${formatMoney(s.unit_price)}</span>
        <button class="rm" title="Remove">✕</button>
      </span>`);
      $chip.find('.rm').on('click', function () {
        selectedIds.delete(id);
        renderSelectedChips();
        recalcTotal();
      });
      $selectedServices.append($chip);

      // Summary line
      const $line = $(`<div style="display:flex;justify-content:space-between;padding:6px 0;">
        <div style="color:#374151">${s.serviceName}</div>
        <div style="font-weight:700">${formatMoney(s.unit_price)}</div>
      </div>`);
      $servicesSummaryWrap.append($line);
    });

    // update consultation visible
    $sumConsultation.text(formatMoney(consultation.unit_price));
    recalcTotal();
  }

  function recalcTotal() {
    let total = parseFloat(consultation.unit_price || 0);
    selectedIds.forEach(id => {
      const s = availableServices[id];
      if (s) total += parseFloat(s.unit_price || 0);
    });
    $totalAmount.text(formatMoney(total));
  }

  // Load consultation fee for hospital
  function loadConsultation() {
    let url = API_BASE + '/get-consultation-fee';
    // send hospital_id if available (server may also read from token)
    if (hospital_id) url += '?hospital_id=' + encodeURIComponent(hospital_id);

    $.ajax({
      url,
      method: 'GET',
      headers: { Authorization: `Bearer ${token}` },
      success: function (res) {
        if (res.status && res.data) {
          // response might be one or an array; controller used ->find() earlier, so handle both
          const row = Array.isArray(res.data) ? res.data[0] : res.data;
          consultation.service_id = row.service_id || null;
          consultation.service_name = row.service_name || 'Consultation';
          consultation.unit_price = parseFloat(row.unit_price || 0).toFixed(2);
          $consultationName.text(consultation.service_name);
          $consultationPrice.text(formatMoney(consultation.unit_price));
          $sumConsultation.text(formatMoney(consultation.unit_price));
          recalcTotal();
        } else {
          // fallback - consultation zero
          $consultationName.text('Consultation');
          consultation.unit_price = 0;
          $consultationPrice.text(formatMoney(0));
          recalcTotal();
        }
      },
      error: function (xhr) {
        console.error('Failed to fetch consultation', xhr);
        $consultationName.text('Consultation');
        consultation.unit_price = 0;
        $consultationPrice.text(formatMoney(0));
      }
    });
  }

  // Load services list for hospital
  function loadServices() {
    let url = API_BASE + '/List-Services-with-Prices-HospitalWise';
    if (hospital_id) url += '?hospital_id=' + encodeURIComponent(hospital_id);

    $.ajax({
      url,
      method: 'GET',
      headers: { Authorization: `Bearer ${token}` },
      success: function (res) {
        if (res.status && res.data && res.data.length) {
          // Fill availableServices and populate select
          availableServices = {};
          $serviceSelect.empty().append('<option value="">Select service to add</option>');
          res.data.forEach(s => {
            // hospital_services table returned: id, service_id, unit_price, plus joined fields serviceName, serviceType
            const id = s.service_id || s.service_id; // ensure service id
            availableServices[id] = {
              service_id: id,
              serviceName: s.serviceName || s.service_name || 'Service',
              serviceType: s.serviceType || s.service_type || 'other',
              unit_price: parseFloat(s.unit_price || s.unitPrice || 0).toFixed(2)
            };
            const optText = `${availableServices[id].serviceName} — ${formatMoney(availableServices[id].unit_price)}`;
            $serviceSelect.append(`<option value="${id}">${optText}</option>`);
          });
        } else {
          $serviceSelect.empty().append('<option value="">No services available</option>');
        }
      },
      error: function (xhr) {
        console.error('Failed to fetch services', xhr);
        $serviceSelect.empty().append('<option value="">Error loading services</option>');
      }
    });
  }

  // Add selected service from dropdown
  $serviceSelect.on('change', function () {
    const val = $(this).val();
    if (!val) return;
    if (!availableServices[val]) {
      alert('Selected service not available');
      $(this).val('');
      return;
    }
    if (!selectedIds.has(val)) {
      selectedIds.add(val);
    }
    renderSelectedChips();
    $(this).val('');
  });

  // Cancel button
  $('#cancelBtn').on('click', function () {
    window.location.href = 'appointments.html';
  });

  // Confirm button -> call Generate-Bill
  $('#confirmBtn').on('click', function () {
    if (!confirm('Are you sure you want to generate a bill for this appointment?')) return;

    // prepare payload
    const servicesArr = Array.from(selectedIds).map(id => parseInt(id, 10));
    // If you want the consultation as separate item in billing_items (server logic currently includes consultation automatically),
    // do NOT include consultation here; server will include it. We will send only selected service ids.
    const payload = {
      appointment_id: appointmentId,
      services: servicesArr
    };

    // Optionally include hospital_id (server reads from token but this helps)
    if (hospital_id) payload.hospital_id = hospital_id;

    $statusHint.text('Generating bill…').css('color', '#6b7280');

    $.ajax({
      url: API_BASE + '/Generate-Bill',
      method: 'POST',
      headers: { Authorization: `Bearer ${token}` },
      contentType: 'application/json',
      data: JSON.stringify(payload),
      success: function (res) {
        if (res.status) {
          $statusHint.text('Bill generated successfully. Redirecting...').css('color', '#16a34a');
          // small delay so user sees message
          setTimeout(() => { window.location.href = 'appointments.html'; }, 900);
        } else {
          $statusHint.text(res.Mssge || res.message || 'Failed to generate bill').css('color', '#dc2626');
          console.error(res);
        }
      },
      error: function (xhr) {
        $statusHint.text('Failed to generate bill. Check console.').css('color', '#dc2626');
        console.error('Generate bill error', xhr);
      }
    });
  });

  // init
  loadConsultation();
  loadServices();
});
