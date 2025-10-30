$(document).ready(function(){
    const token = localStorage.getItem("token");
    if(!token){ window.location.href = "index.html"; return; }

    // Get billing info from URL
    const urlParams = new URLSearchParams(window.location.search);
    const billingId = urlParams.get("billingId");
    if(!billingId){
        alert("Missing billing ID");
        window.location.href = "listPayment.html";
    }

    // Set back button
    $("#backBtn, #cancelBtn").click(function(){ window.location.href = "listPayment.html"; });

    // Fetch billing details from backend (optional: you can pass all via URL)
    $.ajax({
        url: "http://localhost:8080/billing/list-Payments",
        method: "GET",
        headers: { Authorization: `Bearer ${token}` },
        success: function(res){
            if(res.status){
                const bill = res.data.find(b => b.billing_id == billingId);
                if(!bill){ alert("Billing not found"); window.location.href = "listPayment.html"; return; }
                $("#billingId").text(bill.billing_id);
                $("#patientName").text(bill.patient_name);
                $("#patientEmail").text(bill.patient_email);
                $("#totalAmount").text(bill.total_amount);
            }
        },
        error: function(err){ console.error(err); alert("Error fetching billing info"); }
    });

    $("#makePaymentBtn").click(function(){
        const transactionType = $("#transactionType").val();
        if(!transactionType){ alert("Select a transaction type"); return; }

        $.ajax({
            url: "http://localhost:8080/billing/make-Payment",
            method: "POST",
            headers: { Authorization: `Bearer ${token}` },
            data: { bill_id: billingId, transaction_type: transactionType },
            success: function(res){
                if(res.status){
                    alert(res.Mssge || "Payment successful");
                    window.location.href = "listPayment.html";
                } else {
                    alert(res.mssge || res.Error || "Payment failed");
                }
            },
            error: function(err){ console.error(err); alert("Error making payment"); }
        });
    });
});
