// patients.js - JavaScript específico para gestão de pacientes

class PatientsManager {
    constructor() {
        this.initEventListeners();
        this.initDataTable();
        this.initSearch();
    }

    initEventListeners() {
        // Botão de novo paciente
        document.getElementById('newPatientBtn')?.addEventListener('click', () => {
            window.location.href = 'patient_edit.php?action=create';
        });

        // Botão de exportar
        document.getElementById('exportBtn')?.addEventListener('click', () => {
            this.exportData();
        });

        // Formulário de filtro
        document.getElementById('filterForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.applyFilters();
        });

        // Botão de limpar filtros
        document.getElementById('clearFiltersBtn')?.addEventListener('click', () => {
            this.clearFilters();
        });

        // Auto-complete para busca de pacientes
        this.initAutoComplete();
    }

    initDataTable() {
        // Inicializar DataTable se disponível
        if (typeof $.fn.DataTable !== 'undefined') {
            $('#patientsTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.25/i18n/Portuguese-Brasil.json'
                },
                pageLength: 25,
                order: [[1, 'desc']], // Ordenar por data de criação
                responsive: true
            });
        }
    }

    initSearch() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            let searchTimeout;
            
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                
                searchTimeout = setTimeout(() => {
                    this.performSearch(e.target.value);
                }, 500);
            });
        }
    }

    initAutoComplete() {
        const patientSearch = document.getElementById('patientSearch');
        if (patientSearch) {
            patientSearch.addEventListener('input', (e) => {
                const searchTerm = e.target.value.trim();
                
                if (searchTerm.length >= 2) {
                    this.fetchPatientsSuggestions(searchTerm);
                }
            });
        }
    }

    async fetchPatientsSuggestions(searchTerm) {
        try {
            const response = await fetch(`../api/patients.php?action=search&term=${encodeURIComponent(searchTerm)}&limit=10`);
            const patients = await response.json();
            
            this.showSuggestions(patients);
        } catch (error) {
            console.error('Erro ao buscar pacientes:', error);
        }
    }

    showSuggestions(patients) {
        const suggestionsContainer = document.getElementById('suggestionsContainer');
        if (!suggestionsContainer) return;

        suggestionsContainer.innerHTML = '';
        
        if (patients.length === 0) {
            suggestionsContainer.style.display = 'none';
            return;
        }

        patients.forEach(patient => {
            const suggestion = document.createElement('div');
            suggestion.className = 'suggestion-item';
            suggestion.innerHTML = `
                <strong>${this.escapeHtml(patient.full_name)}</strong>
                <small class="text-muted">
                    CPF: ${patient.cpf || 'Não informado'} | 
                    Nasc: ${patient.birth_date ? this.formatDate(patient.birth_date) : 'Não informado'}
                </small>
            `;
            
            suggestion.addEventListener('click', () => {
                window.location.href = `patient_detail.php?id=${patient.id}`;
            });
            
            suggestionsContainer.appendChild(suggestion);
        });

        suggestionsContainer.style.display = 'block';
    }

    async performSearch(searchTerm) {
        try {
            // Mostrar loading
            this.showLoading(true);
            
            // Atualizar URL com parâmetros de busca
            const url = new URL(window.location);
            if (searchTerm) {
                url.searchParams.set('search', searchTerm);
            } else {
                url.searchParams.delete('search');
            }
            url.searchParams.delete('page'); // Resetar página
            
            // Fazer requisição AJAX ou recarregar página
            if (this.useAjaxSearch()) {
                await this.searchViaAjax(searchTerm);
            } else {
                window.location.href = url.toString();
            }
        } catch (error) {
            console.error('Erro na busca:', error);
            alert('Erro ao realizar busca. Tente novamente.');
        } finally {
            this.showLoading(false);
        }
    }

    async searchViaAjax(searchTerm) {
        const response = await fetch(`patients.php?search=${encodeURIComponent(searchTerm)}&ajax=1`);
        const html = await response.text();
        
        // Atualizar tabela com resultados
        this.updateTableWithResults(html);
    }

    updateTableWithResults(html) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newTable = doc.querySelector('#patientsTable');
        
        if (newTable) {
            const currentTable = document.querySelector('#patientsTable');
            currentTable.innerHTML = newTable.innerHTML;
        }
    }

    useAjaxSearch() {
        // Verificar se deve usar AJAX (baseado em preferência ou capacidade)
        return typeof fetch !== 'undefined' && 
               document.querySelector('[data-ajax-search]') !== null;
    }

    applyFilters() {
        const form = document.getElementById('filterForm');
        const formData = new FormData(form);
        
        const params = new URLSearchParams();
        for (const [key, value] of formData.entries()) {
            if (value) {
                params.set(key, value);
            }
        }
        
        window.location.href = `patients.php?${params.toString()}`;
    }

    clearFilters() {
        window.location.href = 'patients.php';
    }

    exportData() {
        const format = prompt('Escolha o formato de exportação:\n1. CSV\n2. Excel\n3. PDF', '1');
        
        let exportUrl;
        switch (format) {
            case '1':
                exportUrl = '../api/export.php?type=patients&format=csv';
                break;
            case '2':
                exportUrl = '../api/export.php?type=patients&format=excel';
                break;
            case '3':
                exportUrl = '../api/export.php?type=patients&format=pdf';
                break;
            default:
                return;
        }
        
        // Adicionar filtros atuais à URL de exportação
        const currentParams = new URLSearchParams(window.location.search);
        currentParams.forEach((value, key) => {
            if (key !== 'page') {
                exportUrl += `&${key}=${encodeURIComponent(value)}`;
            }
        });
        
        window.open(exportUrl, '_blank');
    }

    deletePatient(patientId, patientName) {
        if (confirm(`Tem certeza que deseja excluir o paciente "${patientName}"?\n\nEsta ação não pode ser desfeita.`)) {
            fetch(`../api/patients.php?id=${patientId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Paciente excluído com sucesso!');
                    location.reload();
                } else {
                    alert(`Erro: ${data.error || 'Não foi possível excluir o paciente'}`);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao excluir paciente. Verifique sua conexão.');
            });
        }
    }

    quickEditPatient(patientId) {
        // Abrir modal de edição rápida
        this.openQuickEditModal(patientId);
    }

    async openQuickEditModal(patientId) {
        try {
            const response = await fetch(`../api/patients.php?id=${patientId}`);
            const patient = await response.json();
            
            // Preencher formulário no modal
            this.fillQuickEditForm(patient);
            
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('quickEditModal'));
            modal.show();
        } catch (error) {
            console.error('Erro ao carregar dados do paciente:', error);
            alert('Erro ao carregar dados do paciente');
        }
    }

    fillQuickEditForm(patient) {
        document.getElementById('quickEditId').value = patient.id;
        document.getElementById('quickEditName').value = patient.full_name;
        document.getElementById('quickEditCPF').value = patient.cpf || '';
        document.getElementById('quickEditEmail').value = patient.email || '';
        document.getElementById('quickEditPhone').value = patient.phone || '';
    }

    saveQuickEdit() {
        const form = document.getElementById('quickEditForm');
        const formData = new FormData(form);
        const patientId = formData.get('id');
        
        const data = {
            full_name: formData.get('full_name'),
            cpf: formData.get('cpf'),
            email: formData.get('email'),
            phone: formData.get('phone')
        };
        
        fetch(`../api/patients.php?id=${patientId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Paciente atualizado com sucesso!');
                location.reload();
            } else {
                alert(`Erro: ${data.error}`);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao atualizar paciente');
        });
    }

    showLoading(show) {
        const loadingElement = document.getElementById('loadingIndicator');
        if (loadingElement) {
            loadingElement.style.display = show ? 'block' : 'none';
        }
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('pt-BR');
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Estatísticas em tempo real
    async updatePatientStats() {
        try {
            const response = await fetch('../api/stats.php?period=today');
            const stats = await response.json();
            
            this.updateStatsDisplay(stats);
        } catch (error) {
            console.error('Erro ao atualizar estatísticas:', error);
        }
    }

    updateStatsDisplay(stats) {
        // Atualizar contadores na página
        const elements = {
            'totalPatients': stats.total_patients,
            'examsToday': stats.exams_today,
            'pendingExams': stats.pending_reports
        };
        
        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });
    }
}

// Inicializar quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', () => {
    window.patientsManager = new PatientsManager();
    
    // Atualizar estatísticas a cada 60 segundos
    setInterval(() => {
        window.patientsManager.updatePatientStats();
    }, 60000);
});

// Funções globais para uso em eventos inline
function confirmDeletePatient(patientId, patientName) {
    window.patientsManager.deletePatient(patientId, patientName);
}

function quickEdit(patientId) {
    window.patientsManager.quickEditPatient(patientId);
}

function saveQuickEdit() {
    window.patientsManager.saveQuickEdit();
}

// Máscaras para formulários
function initPatientFormMasks() {
    // CPF
    const cpfInputs = document.querySelectorAll('input[name="cpf"]');
    cpfInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 3) value = value.replace(/^(\d{3})(\d)/, '$1.$2');
            if (value.length > 6) value = value.replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3');
            if (value.length > 9) value = value.replace(/^(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4');
            e.target.value = value.substring(0, 14);
        });
    });

    // Telefone
    const phoneInputs = document.querySelectorAll('input[name="phone"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) value = '(' + value;
            if (value.length > 3) value = value.replace(/^(\d{2})(\d)/, '$1) $2');
            if (value.length > 9) value = value.replace(/(\d{5})(\d)/, '$1-$2');
            e.target.value = value.substring(0, 15);
        });
    });

    // Data de nascimento
    const birthDateInputs = document.querySelectorAll('input[name="birth_date"]');
    birthDateInputs.forEach(input => {
        // Adicionar máscara visual
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 2) value = value.replace(/^(\d{2})(\d)/, '$1/$2');
            if (value.length > 5) value = value.replace(/^(\d{2})\/(\d{2})(\d)/, '$1/$2/$3');
            e.target.value = value.substring(0, 10);
        });
        
        // Definir data máxima (hoje)
        const today = new Date().toISOString().split('T')[0];
        input.max = today;
    });
}

// Validação de formulário
function validatePatientForm() {
    const form = document.getElementById('patientForm');
    if (!form) return true;

    const requiredFields = ['full_name', 'birth_date', 'gender'];
    let isValid = true;
    let errorMessage = '';

    requiredFields.forEach(fieldName => {
        const field = form.querySelector(`[name="${fieldName}"]`);
        if (field && !field.value.trim()) {
            isValid = false;
            field.classList.add('is-invalid');
            
            const fieldLabel = field.previousElementSibling?.textContent || fieldName;
            errorMessage += `• ${fieldLabel} é obrigatório\n`;
        } else if (field) {
            field.classList.remove('is-invalid');
        }
    });

    // Validar CPF se preenchido
    const cpfField = form.querySelector('[name="cpf"]');
    if (cpfField && cpfField.value.trim()) {
        const cpf = cpfField.value.replace(/\D/g, '');
        if (cpf.length !== 11) {
            isValid = false;
            cpfField.classList.add('is-invalid');
            errorMessage += '• CPF deve conter 11 dígitos\n';
        }
    }

    // Validar email se preenchido
    const emailField = form.querySelector('[name="email"]');
    if (emailField && emailField.value.trim()) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(emailField.value)) {
            isValid = false;
            emailField.classList.add('is-invalid');
            errorMessage += '• Email inválido\n';
        }
    }

    if (!isValid) {
        alert('Por favor, corrija os seguintes erros:\n\n' + errorMessage);
    }

    return isValid;
}

// Inicializar quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', () => {
    // Inicializar máscaras
    initPatientFormMasks();
    
    // Adicionar validação ao formulário
    const patientForm = document.getElementById('patientForm');
    if (patientForm) {
        patientForm.addEventListener('submit', function(e) {
            if (!validatePatientForm()) {
                e.preventDefault();
            }
        });
    }
});