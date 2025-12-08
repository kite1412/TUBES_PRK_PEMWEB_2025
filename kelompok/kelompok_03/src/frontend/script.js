// ======================
// PAGE SWITCHING
// ======================
const loginSection = document.getElementById("loginSection");
const registerSection = document.getElementById("registerSection");
const dashboard = document.getElementById("dashboard");

document.getElementById("btnLoginPage").onclick = () => {
    loginSection.classList.remove("hidden");
    registerSection.classList.add("hidden");
    dashboard.classList.add("hidden");
};

document.getElementById("btnRegisterPage").onclick = () => {
    registerSection.classList.remove("hidden");
    loginSection.classList.add("hidden");
    dashboard.classList.add("hidden");
};

// ======================
// LOGIN (dummy dulu)
// ======================
document.getElementById("loginBtn").onclick = () => {
    loginSection.classList.add("hidden");
    dashboard.classList.remove("hidden");
};

// ======================
// REGISTER (dummy)
// ======================
document.getElementById("registerBtn").onclick = () => {
    alert("Register berhasil (dummy). Silakan login.");
};

// ======================
// DATA PEGAWAI (LOCAL ARRAY DULU)
// ======================

let employees = [
    { name: "Rina", pos: "Manager" },
    { name: "Doni", pos: "Staff" }
];

function loadTable() {
    const tbody = document.getElementById("empData");
    tbody.innerHTML = "";

    employees.forEach((emp, i) => {
        const row = `
            <tr>
                <td>${emp.name}</td>
                <td>${emp.pos}</td>
                <td>
                    <button class="action-btn edit-btn" onclick="editEmp(${i})">Edit</button>
                    <button class="action-btn delete-btn" onclick="deleteEmp(${i})">Delete</button>
                </td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
}
loadTable();

// ======================
// ADD EMPLOYEE
// ======================
document.getElementById("addEmpBtn").onclick = () => {
    const name = document.getElementById("empName").value;
    const pos = document.getElementById("empPos").value;

    if (name.trim() === "" || pos.trim() === "") {
        alert("Isi semua data!");
        return;
    }

    employees.push({ name, pos });
    loadTable();
};

// ======================
// EDIT EMPLOYEE
// ======================
function editEmp(i) {
    const newName = prompt("Edit name:", employees[i].name);
    const newPos = prompt("Edit position:", employees[i].pos);

    if (newName && newPos) {
        employees[i].name = newName;
        employees[i].pos = newPos;
        loadTable();
    }
}

// ======================
// DELETE EMPLOYEE
// ======================
function deleteEmp(i) {
    if (confirm("Hapus data?")) {
        employees.splice(i, 1);
        loadTable();
    }
}
