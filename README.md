# 🏥 Hospital Management System

A **multi-tenant Hospital Management System** built using **CodeIgniter (PHP)** framework.  
This project is designed to handle the operations of multiple hospitals while maintaining role-based access for administrators, doctors, and patients.



## 🧩 Database Overview

The system uses multiple interconnected tables to manage data flow between hospitals, users, appointments, and visit records.

### **1. Users**
Contains details of all users, including:
- **Admin (role = 0)**
- **Doctor (role = 1)**
- **Patient (role = 2)**
- **SuperAdmin (role = 3)**  

> 💡Messed up the Users table a bit , First it was created for oly booking an appointment so made a simple and single table ,Later as application grown and feature was added it wasn't appropriate.Had a discussion with kartikeya sir he told to continue with same table so continued as such

---

### **2. Appointments**
Holds details of all appointments, including booking information, schedule, doctor, and patient linkage.

---

### **3. Visit_Records**
Stores detailed medical information related to completed appointments — such as diagnosis, prescribed medicines, and follow-up recommendations.

---

### **4. Hospitals**
Contains hospital-specific information such as name, address, contact, and admin details.

---
---

## 🚀 Key Features

1. **Appointment Booking System**  
   - Patients can book appointments with doctors across any hospital.

2. **Doctor Dashboard**  
   - Doctors can view, confirm, reschedule, and complete appointments.
   - Doctors can add prescriptions for completed appointments, which patients can download as PDF.

3. **Prescription & Medical History**  
   - Patients can access their visit history and download prescriptions.  
   - They can also track their **weight** and **blood pressure** visually using graphs.

4. **Admin and SuperAdmin Roles**  
   - **Admins** manage hospitals, doctors, and patients within their hospital.  
   - **SuperAdmins** have access to all hospitals and complete control over the system.

---

