<?php
require dirname(__DIR__) . '/src/bootstrap.php';
admin_logout();
flash('success','Sesión cerrada correctamente.');
redirect('admin/login.php');
