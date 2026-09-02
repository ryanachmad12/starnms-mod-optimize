import './bootstrap';

const modal=document.querySelector('#projectModal'),form=document.querySelector('#projectForm');
document.querySelector('#addProject').addEventListener('click',()=>{form.reset();form.action='/projects';document.querySelector('#projectMethod').value='POST';document.querySelector('#projectModalTitle').textContent='Add project';form.elements.is_active.checked=true;modal.showModal()});
document.querySelectorAll('.close-project').forEach(b=>b.addEventListener('click',()=>modal.close()));
document.querySelectorAll('.edit-project').forEach(b=>b.addEventListener('click',()=>{const p=JSON.parse(b.dataset.project);form.reset();form.action=`/projects/${p.id}`;document.querySelector('#projectMethod').value='PUT';document.querySelector('#projectModalTitle').textContent='Edit project';['name','customer_name','location','maintenance_interval_months','notes'].forEach(k=>form.elements[k].value=p[k]??'');['contract_start_date','contract_end_date','maintenance_anchor_date'].forEach(k=>form.elements[k].value=(p[k]||'').substring(0,10));form.elements.is_active.checked=!!p.is_active;modal.showModal()}));
import './global-alerts';
