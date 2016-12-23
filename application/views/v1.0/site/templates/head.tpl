<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>{$settings.page_title} &mdash; {$settings.slogan}</title>
  {include file='meta.tpl'}
  <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
  <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
  <!--[if lt IE 9]>
  <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
  <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
  <![endif]-->
</head>
<body class="hold-transition {if $user}skin-green sidebar-mini{else}login-page{/if}">
{if $user}
<div class="wrapper">


  <!-- Main Header -->
  <header class="main-header">

    <!-- Logo -->
    <a href="{$settings.page_url}" class="logo">
      <!-- mini logo for sidebar mini 50x50 pixels -->
      <span class="logo-mini"><b>CF</b>M</span>
      <!-- logo for regular state and mobile devices -->
      <span class="logo-lg"><b>CashFlow</b>Manager</span>
    </a>

    <!-- Header Navbar -->
    <nav class="navbar navbar-static-top" role="navigation">
      <!-- Sidebar toggle button-->
      <a href="{$settings.page_url}" class="sidebar-toggle" data-toggle="offcanvas" role="button">
        <span class="sr-only">Toggle navigation</span>
      </a>
      <!-- Navbar Right Menu -->
      <div class="navbar-custom-menu">
        <ul class="nav navbar-nav">
          <li>
            <a href="">{$lng_penztarca}: <strong>0 {$settings.valuta}</strong></a>
          </li>

          {if false}
          <!-- Messages: style can be found in dropdown.less-->
          <li class="dropdown messages-menu">
            <!-- Menu toggle button -->
            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
              <i class="fa fa-envelope-o"></i>
              <span class="label label-success">4</span>
            </a>
            <ul class="dropdown-menu">
              <li class="header">You have 4 messages</li>
              <li>
                <!-- inner menu: contains the messages -->
                <ul class="menu">
                  <li><!-- start message -->
                    <a href="#">
                      <div class="pull-left">
                        <!-- User Image -->
                        <img src="dist/img/user2-160x160.jpg" class="img-circle" alt="User Image">
                      </div>
                      <!-- Message title and timestamp -->
                      <h4>
                        Support Team
                        <small><i class="fa fa-clock-o"></i> 5 mins</small>
                      </h4>
                      <!-- The message -->
                      <p>Why not buy a new awesome theme?</p>
                    </a>
                  </li>
                  <!-- end message -->
                </ul>
                <!-- /.menu -->
              </li>
              <li class="footer"><a href="#">See All Messages</a></li>
            </ul>
          </li>
          <!-- /.messages-menu -->

          <!-- Notifications Menu -->
          <li class="dropdown notifications-menu">
            <!-- Menu toggle button -->
            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
              <i class="fa fa-bell-o"></i>
              <span class="label label-warning">10</span>
            </a>
            <ul class="dropdown-menu">
              <li class="header">You have 10 notifications</li>
              <li>
                <!-- Inner Menu: contains the notifications -->
                <ul class="menu">
                  <li><!-- start notification -->
                    <a href="#">
                      <i class="fa fa-users text-aqua"></i> 5 new members joined today
                    </a>
                  </li>
                  <!-- end notification -->
                </ul>
              </li>
              <li class="footer"><a href="#">View all</a></li>
            </ul>
          </li>
          <!-- Tasks Menu -->
          <li class="dropdown tasks-menu">
            <!-- Menu Toggle Button -->
            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
              <i class="fa fa-flag-o"></i>
              <span class="label label-danger">9</span>
            </a>
            <ul class="dropdown-menu">
              <li class="header">You have 9 tasks</li>
              <li>
                <!-- Inner menu: contains the tasks -->
                <ul class="menu">
                  <li><!-- Task item -->
                    <a href="#">
                      <!-- Task title and progress text -->
                      <h3>
                        Design some buttons
                        <small class="pull-right">20%</small>
                      </h3>
                      <!-- The progress bar -->
                      <div class="progress xs">
                        <!-- Change the css width attribute to simulate progress -->
                        <div class="progress-bar progress-bar-aqua" style="width: 20%" role="progressbar" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100">
                          <span class="sr-only">20% Complete</span>
                        </div>
                      </div>
                    </a>
                  </li>
                  <!-- end task item -->
                </ul>
              </li>
              <li class="footer">
                <a href="#">View all tasks</a>
              </li>
            </ul>
          </li>
          {/if}
          <!-- User Account Menu -->
          <li class="dropdown user user-menu">
            <!-- Menu Toggle Button -->
            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
              <!-- hidden-xs hides the username on small devices so only the image appears. -->
              <span class="hidden-xs">{$user.data.name}</span>
            </a>
            <ul class="dropdown-menu">
              <!-- The user image in the menu -->
              <li class="user-header">
                <p>
                 {$user.data.name}
                  <small>{$user.data.email}</small>
                </p>
              </li>
              <!-- Menu Footer-->
              <li class="user-footer">
                <div class="pull-left">
                  <a href="/settings" class="btn btn-default btn-flat">{$lng_beallitasok}</a>
                </div>
                <div class="pull-right">
                  <a href="/?logout=1" class="btn btn-default btn-flat">{$lng_kijelentkezes}</a>
                </div>
              </li>
            </ul>
          </li>
          <!-- Control Sidebar Toggle Button -->
          <!--
          <li>
            <a href="#" data-toggle="control-sidebar"><i class="fa fa-gears"></i></a>
          </li>
          -->
        </ul>
      </div>
    </nav>
  </header>

  <!-- Left side column. contains the logo and sidebar -->

  <aside class="main-sidebar">

    <!-- sidebar: style can be found in sidebar.less -->
    <section class="sidebar">
      <!-- Sidebar Menu -->
      <ul class="sidebar-menu">
        <li class="{if $homepage}active{/if}"><a href="{$settings.page_url}"><i class="fa fa-dashboard"></i> <span>{$lng_attekintes}</span></a></li>
        <li class=""><a href="/settings"><i class="fa fa-gears"></i> <span>{$lng_beallitasok}</span></a></li>

        <li class="header">{$lng_forgalom}</li>
        <li class=""><a href="/cash/new"><i class="fa fa-plus-circle"></i> <span>{$lng_uj_bejegyzes}</span></a></li>
        <li class=""><a href="/stat"><i class="fa fa-pie-chart"></i> <span>{$lng_statisztika}</span></a></li>
        <li class="header">{$lng_tranzakcio}</li>
        <li class="treeview">
          <a href="/transactions/categories"><i class="fa fa-bars"></i> <span>{$lng_kategoriak}</span>
            <span class="pull-right-container">
              <i class="fa fa-angle-left pull-right"></i>
            </span>
          </a>
          <ul class="treeview-menu">
            <li><a href="/transactions/categories/">{$lng_teljeslista}</a></li>
            <li><a href="/transactions/categories/?v=create">{$lng_uj_kategoria}</a></li>
          </ul>
        </li>
        <li class="treeview">
          <a href="/transactions/groupes"><i class="fa fa-users"></i> <span>{$lng_csoportok}</span>
            <span class="pull-right-container">
              <i class="fa fa-angle-left pull-right"></i>
            </span>
          </a>
          <ul class="treeview-menu">
            <li><a href="/transactions/groupes/">{$lng_teljeslista}</a></li>
            <li><a href="/transactions/groupes/?v=create">{$lng_uj_csoport}</a></li>
          </ul>
        </li>

      </ul>
      <!-- /.sidebar-menu -->
    </section>
    <!-- /.sidebar -->
  </aside>

  <div class="content-wrapper">
  {else}

  <div class="login-box">
    <div class="login-logo">
      <a href="{$settings.page_url}"><b>CashFlow</b>Manager</a>
    </div>

    <!-- /.login-logo -->
    <div class="login-box-body">

      <form action="/forms/auth" method="post">
        <input type="hidden" name="return" value="{$smarty.const.CURRENT_PAGE}">
        <input type="hidden" name="form" value="1">
        <input type="hidden" name="for" value="user">
        <input type="hidden" name="user_group" value="0">
        <input type="hidden" name="session_path" value="/welcome">

        <div class="form-group has-feedback">
          <input type="email" class="form-control" name="email" placeholder="{$lng_email}">
          <span class="glyphicon glyphicon-envelope form-control-feedback"></span>
        </div>
        <div class="form-group has-feedback">
          <input type="password" class="form-control" name="pw" placeholder="{$lng_jelszo}">
          <span class="glyphicon glyphicon-lock form-control-feedback"></span>
        </div>
        <div class="row">
          <!-- /.col -->
          <div class="col-xs-12">
            <button type="submit" class="btn btn-primary btn-block btn-flat">{$lng_bejelentkezes}</button>
          </div>
          <!-- /.col -->
        </div>
      </form>

      <!-- <a href="/register" class="text-center">{$lng_regisztracio}</a>-->

    </div>
    <br>
    <p class="login-box-msg">{$lng_poweredby}</p>
    <!-- /.login-box-body -->
  </div>

  {/if}
