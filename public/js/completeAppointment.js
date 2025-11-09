$(document).ready(function () {

const token = localStorage.getItem("token");
if(!token){ location.href="index.html"; }

const urlParams = new URLSearchParams(window.location.search);
const appointmentId = urlParams.get('appointmentId');
$("#appointmentId").val(appointmentId);

if(!appointmentId){
 alert("Missing appointment ID");
 location.href="appointments.html";
}

let diagnosisList = [];

// ✅ Fetch master diagnosis list
// ✅ Fetch master diagnosis list
$.ajax({
 url:"http://localhost:8080/appointment/diagnosis-List",
 method:"GET",
 headers:{Authorization:`Bearer ${token}`},
 success:res=>{
   if(res.status && Array.isArray(res.data)){
     diagnosisList = res.data;

     // ✅ Re-render existing diagnosis selects after API load
     $(".diagnosis_select").each(function(){
        let selected = $(this).val();
        let options = `<option value="">--Select--</option>`;
        options += diagnosisList.map(d => `<option value="${d.id}">${d.name}</option>`).join("");
        options += `<option value="new">+ Add New</option>`;
        $(this).html(options).val(selected);
     });
   }
 },
 error:()=>alert("Unable to load diagnosis list")
});


// ✅ Add Complaint Row
function addComplaintRow(){
 $("#complaintsSection").append(`
 <div class="complaint-row">
   <label>Complaint</label>
   <input type="text" class="complaint_name" placeholder="Eg: Fever" required>

   <label>Description</label>
   <textarea class="complaint_desc"></textarea>

   <div class="row-flex">
     <div>
       <label>Severity</label>
       <select class="complaint_severity">
         <option value="low">Low</option>
         <option value="medium">Medium</option>
         <option value="high">High</option>
       </select>
     </div>
     <div>
       <label>Days</label>
       <input type="number" class="complaint_days" min="1">
     </div>
   </div>

   <button type="button" class="remove-btn removeComplaint">Remove</button>
   <hr>
 </div>`);
}
$("#addComplaint").click(addComplaintRow);
$(document).on("click",".removeComplaint",function(){ $(this).parent().remove();});
addComplaintRow();

// ✅ Add Diagnosis Row
function addDiagnosisRow(){
 let options = diagnosisList.map(d=>`<option value="${d.id}">${d.name}</option>`).join("");

 $("#diagnosisSection").append(`
 <div class="diagnosis-row">
   <label>Diagnosis</label>
   <select class="diagnosis_select">
     <option value="">--Select--</option>
     ${options}
     <option value="new">+ Add New</option>
   </select>

   <input type="text" class="diagnosis_new_name" placeholder="Enter new diagnosis" style="display:none;">

   <label>Notes</label>
   <textarea class="diagnosis_notes"></textarea>

   <button type="button" class="remove-btn removeDiagnosis">Remove</button>
   <hr>
 </div>`);
}
$("#addDiagnosis").click(addDiagnosisRow);
$(document).on("click",".removeDiagnosis",function(){ $(this).parent().remove();});
addDiagnosisRow();

// ✅ show text box if new diagnosis selected
$(document).on("change",".diagnosis_select",function(){
 if($(this).val()==="new"){
   $(this).siblings(".diagnosis_new_name").show();
 } else {
   $(this).siblings(".diagnosis_new_name").hide().val("");
 }
});

// ✅ Add Prescription Row
function addPrescriptionRow(){
 $("#prescriptionSection").append(`
 <div class="pres-row">
   <label>Medicine</label>
   <input type="text" class="pres_med" required>

   <label>Dosage</label>
   <input type="text" class="pres_dosage" required>

   <label>Frequency</label>
   <input type="text" class="pres_freq" placeholder="Eg: 2 times/day" required>

   <label>Duration</label>
   <input type="text" class="pres_duration" placeholder="Eg: 5 days" required>

   <label>Instructions</label>
   <textarea class="pres_instr"></textarea>

   <button type="button" class="remove-btn removePrescription">Remove</button>
   <hr>
 </div>`);
}
$("#addPrescription").click(addPrescriptionRow);
$(document).on("click",".removePrescription",function(){ $(this).parent().remove();});
addPrescriptionRow();

// ✅ Form Submit
$("#appointmentCompleteForm").on("submit",function(e){
 e.preventDefault();

 let complaints=[], diagnoses=[], prescriptions=[];

 $(".complaint-row").each(function(){
  complaints.push({
   complaint:$(this).find(".complaint_name").val(),
   description:$(this).find(".complaint_desc").val(),
   severity:$(this).find(".complaint_severity").val(),
   days:$(this).find(".complaint_days").val()
  });
 });

 $(".diagnosis-row").each(function(){
  let dSel = $(this).find(".diagnosis_select").val();
  let newName = $(this).find(".diagnosis_new_name").val();

  diagnoses.push({
    diagnosis_id: dSel !== "new" ? dSel : null,
    name: dSel === "new" ? newName : null,
    notes: $(this).find(".diagnosis_notes").val()
  });
 });

 $(".pres-row").each(function(){
  prescriptions.push({
    medicine_name:$(this).find(".pres_med").val(),
    dosage:$(this).find(".pres_dosage").val(),
    frequency:$(this).find(".pres_freq").val(),
    duration:$(this).find(".pres_duration").val(),
    instructions:$(this).find(".pres_instr").val()
  });
 });

 const payload = {
   appointment_id: appointmentId,
   weight: $("#weight").val(),
   bp_systolic: $("#bp_systolic").val(),
   bp_diastolic: $("#bp_diastolic").val(),
   doctor_comment: $("#doctor_comment").val(),
   complaints,
   diagnoses,
   prescriptions
 };

 $.ajax({
   url:"http://localhost:8080/appointment/complete-Appointment",
   method:"POST",
   headers:{Authorization:`Bearer ${token}`},
   data:payload,
   success:res=>{
     if(res.status){
       alert("Appointment Completed ✅");
       location.href=`generateBill.html?appointmentId=${appointmentId}`;
     } else alert(res.Mssge || res.Error);
   },
   error:x=>console.error(x)
 });

});

});
