import './bootstrap';

const modal=document.querySelector('#userModal'),form=document.querySelector('#userForm');
document.querySelector('#addUser').addEventListener('click',()=>{form.reset();form.action='/users';document.querySelector('#userMethod').value='POST';document.querySelector('#userModalTitle').textContent='Add user';document.querySelector('#userPassword').required=true;document.querySelector('#userPasswordConfirmation').required=true;modal.showModal()});
document.querySelectorAll('.close-user').forEach(b=>b.addEventListener('click',()=>modal.close()));
document.querySelectorAll('.edit-user').forEach(b=>b.addEventListener('click',()=>{const u=JSON.parse(b.dataset.user);form.reset();form.action=`/users/${u.id}`;document.querySelector('#userMethod').value='PUT';document.querySelector('#userModalTitle').textContent='Edit user';['name','username','email','role'].forEach(k=>form.elements[k].value=u[k]??'');form.elements.is_active.checked=!!u.is_active;document.querySelector('#userPassword').required=false;document.querySelector('#userPasswordConfirmation').required=false;modal.showModal()}));
import './global-alerts';
