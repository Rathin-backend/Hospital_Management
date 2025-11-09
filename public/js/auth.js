

$(document).ready(function () {
  const API = "http://localhost:8080";

  
  $(".tab-btn").on("click", function () {
    const view = $(this).data("view");
    $(".tab-btn").removeClass("active");
    $(this).addClass("active");
    $(".view").removeClass("active");
    $("#view-" + view).addClass("active");
    $("#loginMsg, #registerMsg").text("").removeClass("error success");
  });

  
  function showMsg($el, text, type = "error") {
    $el.removeClass("error success").addClass(type).text(text || "").show();
  }

  function bearer() {
    const t = localStorage.getItem("token");
    return t ? { Authorization: "Bearer " + t } : {};
  }

  function cardHTML({ mapping_id, hospital_id, hospital_name, role }) {
    const roleLabel = Number(role) === 1 ? "Doctor" : "Admin";
    const roleIcon = Number(role) === 1 ? "stethoscope" : "user-shield";
    return `
      <div class="card" data-mid="${mapping_id}">
        <span class="badge"><i class="fa-solid fa-${roleIcon}"></i>&nbsp;${roleLabel}</span>
        <h3>${hospital_name}</h3>
        <p>ID: ${hospital_id}</p>
      </div>`;
  }

  
  $("#btnLogin").on("click", function () {
    const role = $("#loginRole").val();
    const email = $("#loginEmail").val().trim();
    const password = $("#loginPassword").val().trim();
    const $msg = $("#loginMsg");

    if (!role || !email || !password) return showMsg($msg, "All fields are required");

    const isPatientOrSA = role === "2" || role === "3";
    const url = isPatientOrSA ? `${API}/PatientandSuperAdminlogin` : `${API}/login`;

    $.ajax({
      url,
      method: "POST",
      contentType: "application/json", 
      data: JSON.stringify({ email, password }), 
      success: function (res) {
        if (!res?.status) return showMsg($msg, res?.message || "Login failed");

        localStorage.setItem("token", res.token || "");

        if (isPatientOrSA) {
          if (res.user) {
            localStorage.setItem("role", String(res.user.role ?? ""));
            if (res.user.hospital_id !== undefined) {
              localStorage.setItem("hospital_id", String(res.user.hospital_id ?? ""));
            }
          }
          return (window.location.href = "dashboard.html");
        }

        
        $.ajax({
          url: `${API}/auth/hospitals`,
          method: "GET",
          headers: bearer(),
          success: function (hres) {
            const list = hres?.hospitals || [];
            if (!list.length) return showMsg($msg, "No hospitals mapped. Contact SuperAdmin.");

            const $grid = $("#cardGrid").empty();
            list.forEach((m) => $grid.append(cardHTML(m)));
            $("#selectOverlay").fadeIn(150);

            $("#cardGrid").off("click").on("click", ".card", function () {
              const mid = $(this).data("mid");
              $.ajax({
                url: `${API}/auth/set-active-hospital/${mid}`,
                method: "POST",
                headers: bearer(),
                success: function (r) {
                  if (!r?.status) return showMsg($msg, r?.message || "Failed to select hospital");

                  localStorage.setItem("token", r.token || "");
                  localStorage.setItem("role", r.user?.role ?? "");
                  localStorage.setItem("hospital_id", r.user?.hospital_id ?? "");
                  window.location.href = "dashboard.html";
                },
                error: () => showMsg($msg, "Hospital select error"),
              });
            });
          },
          error: () => showMsg($msg, "Failed to fetch hospitals"),
        });
      },
      error: (xhr) => showMsg($msg, xhr?.responseJSON?.message || "Login error")
    });
  });

  
  $("#btnRegister").on("click", function () {
    const name = $("#regName").val().trim();
    const gender = $("#regGender").val();
    const email = $("#regEmail").val().trim();
    const password = $("#regPassword").val().trim();
    const phone_no = $("#regPhone").val().trim();
    const $msg = $("#registerMsg");

    if (!name || !gender || !email || !password) return showMsg($msg, "All fields required");

    $.ajax({
      url: `${API}/register`,
      method: "POST",
      contentType: "application/json", 
      data: JSON.stringify({ name, gender, email, password, phone_no }),
      success: function (res) {
        if (!res?.status) return showMsg($msg, res?.message || "Registration failed");
        showMsg($msg, "Registered successfully! Login now.", "success");
        setTimeout(() => $(".tab-btn[data-view='login']").click(), 800);
      },
      error: (xhr) => {
        const m = xhr?.responseJSON?.error
          ? Object.values(xhr.responseJSON.error).join(", ")
          : "Registration error";
        showMsg($msg, m);
      },
    });
  });

  $("#selectOverlay").hide();
});
