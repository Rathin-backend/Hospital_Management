$(document).ready(function () {
  const token = localStorage.getItem("token");
  const role = localStorage.getItem("role");
  const apiBase = "http://localhost:8080";

  let selectedDate = null;
  let selectedSlot = null;

  if (!token) {
    alert("Login required");
    window.location.href = "index.html";
    return;
  }

  if (role !== "2") {
    $("#bookForm input, #bookForm select, #bookForm button").prop("disabled", true);
    $("#responseMessage").addClass("error").text("⚠️ Only patients can book appointments.");
  }

  // === Load Hospitals ===
  function loadHospitals() {
    $.ajax({
      url: `${apiBase}/hospital/list-all-Hospitals`,
      method: "GET",
      headers: { Authorization: `Bearer ${token}` },
      success: function (res) {
        $("#hospitalSelect").empty().append(`<option value="">-- Select Hospital --</option>`);
        (res.data || []).forEach(h => {
          $("#hospitalSelect").append(`<option value="${h.id}">${h.name}</option>`);
        });
      }
    });
  }

  // === Load Doctors from /api/list-Doctors ===
  function loadDoctors(hospitalId, department = "all") {
    if (!hospitalId) return;

    $.ajax({
      url: `${apiBase}/api/list-Doctors`,
      method: "GET",
      headers: { Authorization: `Bearer ${token}` },
      data: { hospital_id: hospitalId },
      success: function (res) {
        $("#doctorSelect").empty().append(`<option value="">-- Select Doctor --</option>`);

        (res.data || []).forEach(doc => {
          if (department === "all" ||
            (doc.expertise && doc.expertise.toLowerCase().includes(department.toLowerCase()))) {
            $("#doctorSelect").append(
              `<option value="${doc.id}">${doc.name} (${doc.expertise || "N/A"})</option>`
            );
          }
        });
      },
      error: function () {
        $("#responseMessage").addClass("error").text("Failed to load doctors.");
      }
    });
  }

  // === Generate next 14 days (No Sundays) ===
  function generateDates() {
    const strip = $("#dateStrip").empty();
    const today = new Date();
    let count = 0;

    while (count < 14) {
      today.setDate(today.getDate() + 1);
      if (today.getDay() === 0) continue; // skip Sunday

      const dateStr = today.toISOString().split("T")[0];
      const dayShort = today.toLocaleDateString("en-US", { weekday: "short" });
      const dateNum = today.getDate();

      strip.append(`
        <div class="date-card" data-date="${dateStr}">
          <div>${dayShort}</div>
          <div>${dateNum}</div>
        </div>
      `);

      count++;
    }
  }

  // === Fetch Availability ===
  function fetchAvailability(hospitalId, doctorId, date) {
    $.ajax({
      url: `${apiBase}/appointment/get-Doctor-Availability`,
      method: "GET",
      headers: { Authorization: `Bearer ${token}` },
      data: { hospital_id: hospitalId, DoctorId: doctorId, Date: date },
      success: function (res) {
        const container = $("#slotsContainer").empty();
        const slots = res.data || [];

        if (!slots.length) {
          container.html("<p style='color:#6b7280'>No slots available</p>");
          return;
        }

        slots.forEach(time => {
          container.append(`
            <div class="slot-card" data-time="${time}">
              ${convertTo12(time)}
            </div>
          `);
        });
      }
    });
  }

  // === Convert HH:MM:SS → 12 hour (hh:mm AM/PM) ===
  function convertTo12(time) {
    const [h, m] = time.split(":");
    let hour = parseInt(h);
    const ampm = hour >= 12 ? "PM" : "AM";
    hour = hour % 12 || 12;
    return `${hour}:${m} ${ampm}`;
  }

  // === EVENTS ===
  $("#hospitalSelect").change(() =>
    loadDoctors($("#hospitalSelect").val(), $("#departmentSelect").val())
  );

  $("#departmentSelect").change(() =>
    loadDoctors($("#hospitalSelect").val(), $("#departmentSelect").val())
  );

  $("#doctorSelect").change(() => {
    if (selectedDate)
      fetchAvailability($("#hospitalSelect").val(), $("#doctorSelect").val(), selectedDate);
  });

  $(document).on("click", ".date-card", function () {
    $(".date-card").removeClass("selected");
    $(this).addClass("selected");

    selectedDate = $(this).data("date");
    if ($("#doctorSelect").val())
      fetchAvailability($("#hospitalSelect").val(), $("#doctorSelect").val(), selectedDate);
  });

  $(document).on("click", ".slot-card", function () {
    $(".slot-card").removeClass("selected");
    $(this).addClass("selected");

    // ✅ Backend expects "10:00 AM" not "10:00:00"
    const raw = $(this).data("time");
    selectedSlot = convertTo12(raw);
  });

  // === Submit Booking ===
  $("#bookForm").submit(function (e) {
    e.preventDefault();

    if (!$("#hospitalSelect").val() || !$("#doctorSelect").val() || !selectedDate || !selectedSlot) {
      $("#responseMessage").addClass("error").text("Select all fields");
      return;
    }

    $.ajax({
      url: `${apiBase}/appointment/Book-appointment`,
      method: "POST",
      headers: { Authorization: `Bearer ${token}` },
      data: {
        hospital_id: $("#hospitalSelect").val(),
        doctorId: $("#doctorSelect").val(),
        appointment_date: selectedDate,
        appointment_startTime: selectedSlot
      },
      success: function (res) {
        if (res.status) {
          $("#responseMessage").removeClass("error").addClass("success").text("✅ Appointment booked successfully!");
          setTimeout(() => location.href = "appointments.html", 1500);
        } else {
          $("#responseMessage").removeClass("success").addClass("error").text(res.message || "Error");
        }
      }
    });
  });

  $("#backBtn").click(() => location.href = "dashboard.html");

  // Init
  if (role === "2") {
    loadHospitals();
    generateDates();
  }
});
