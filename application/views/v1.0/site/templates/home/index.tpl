<section class="content-header">
  <h1>Gépház</h1>
</section>
<section class="content">

  <div class="row">
    <div class="col-md-4">
      <div class="info-box">
        <span class="info-box-icon bg-green"><i class="ion ion-log-in"></i></span>
        <div class="info-box-content">
          <span class="info-box-text">{'Y'|date} bevétel</span>
          <span class="info-box-number text-big">{$cash_info.income|number_format:0:",":"."} <small>Ft</small></span>
        </div>
        <!-- /.info-box-content -->
      </div>
    </div>
    <div class="col-md-4">
      <div class="info-box">
        <span class="info-box-icon bg-orange"><i class="ion ion-log-out"></i></span>
        <div class="info-box-content">
          <span class="info-box-text">{'Y'|date} kiadás</span>
          <span class="info-box-number text-big">{$cash_info.outgo|number_format:0:",":"."} <small>Ft</small></span>
        </div>
        <!-- /.info-box-content -->
      </div>
    </div>
    <div class="col-md-4">
      <div class="info-box">
        <span class="info-box-icon bg-red"><i class="ion ion-cash"></i></span>
        <div class="info-box-content">
          <span class="info-box-text">Rendelkezésre álló egyenleg</span>
          <span class="info-box-number text-big">{$cash_info.avaiable|number_format:0:",":"."} <small>Ft</small></span>
        </div>
        <!-- /.info-box-content -->
      </div>
    </div>
  </div>
  <div class="row">
    <div class="col-md-6">
      <div class="info-box">
        <span class="info-box-icon bg-aqua"><i class="ion ion-log-in"></i></span>
        <div class="info-box-content">
          <span class="info-box-text">Összes bevétel</span>
          <span class="info-box-number text-medium">{$cash_info.all_income|number_format:0:",":"."} <small>Ft</small></span>
        </div>
        <!-- /.info-box-content -->
      </div>
    </div>
    <div class="col-md-6">
      <div class="info-box">
        <span class="info-box-icon bg-aqua"><i class="ion ion-log-out"></i></span>
        <div class="info-box-content">
          <span class="info-box-text">Összes kiadás</span>
          <span class="info-box-number text-medium">{$cash_info.all_outgo|number_format:0:",":"."} <small>Ft</small></span>
        </div>
        <!-- /.info-box-content -->
      </div>
    </div>
  </div>
  <div class="row">
    <div class="col-md-6">
      <form action="" method="post">
        <div class="box box-success">
          <div class="box-header with-border">
            <i class="fa fa-caret-up"></i> <h3 class="box-title">Bevétel rögzítése</h3>
          </div>
          <div class="box-body">
            <div class="form-group">
              <label for="in_title">Megnevezés</label>
              <input type="text" name="title" class="form-control" id="in_title" placeholder="">
            </div>
            <div class="form-group">
              <label for="in_date">Tárgynap</label>
              <input type="date" name="date" class="form-control" id="in_date" placeholder="" value="{'Y-m-d'|date}">
            </div>
            <div class="form-group">
              <label for="in_cash">Összeg</label>
              <input type="number" name="cash" class="form-control" step="1" value="0" id="in_cash" placeholder="">
            </div>
            <div class="form-group">
              <label for="in_group">Forrás csoport</label>
              <select name="group" class="form-control" id="in_group">
                <option value="">-- válasszon --</option>
                {foreach from=$income_groups key=ik item=ig}
                <option value="{$ik}">{$ig}</option>
                {/foreach}
              </select>
            </div>
            <div class="form-group">
              <label for="in_holder">Pénz gyűjtő</label>
              <select name="holder" class="form-control" id="in_holder">
                <option value="">-- válasszon --</option>
                {foreach from=$cash_holders key=ik item=ig}
                <option value="{$ik}">{$ig}</option>
                {/foreach}
              </select>
            </div>
          </div>
          <div class="box-footer">
            <button type="submit" name="income" value="1" class="btn btn-success pull-right">Bevétel rögzítése</button>
          </div>
        </div>
      </form>
      <div class="box">
        <div class="box-header with-border">
          <i class="fa fa-list"></i> <h3 class="box-title">Utoljára rögzített bevételek</h3>
        </div>
        <div class="box-body">
          <table class="table table-bordered table-striped">
            <thead>
              <tr>
                <th>ID</th>
                <th>Megjegyzés</th>
                <th>Összeg</th>
                <th>Dátum</th>
              </tr>
            </thead>
            <tbody>
              {foreach from=$trans_last_income item=i}
              <tr>
                <td>#{$i.id}</td>
                <td><strong>{$i.comment}</strong></td>
                <td>{$i.amount|number_format:0:",":"."} Ft</td>
                <td>{$i.trans_date|date_format:"%Y. %m. %d."}</td>
              </tr>
              {/foreach}
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <form action="" method="post">
        <div class="box box-warning">
          <div class="box-header with-border">
            <i class="fa fa-caret-down"></i> <h3 class="box-title">Kiadás rögzítése</h3>
          </div>
          <div class="box-body">
            <div class="form-group">
              <label for="out_title">Megnevezés</label>
              <input type="text" name="title" class="form-control" id="out_title" placeholder="">
            </div>
            <div class="form-group">
              <label for="out_date">Tárgynap</label>
              <input type="date" name="date" class="form-control" id="out_date" placeholder="">
            </div>
            <div class="form-group">
              <label for="out_cash">Összeg</label>
              <input type="number" name="cash" class="form-control" step="1" value="0" id="out_cash" placeholder="">
            </div>
            <div class="form-group">
              <label for="out_group">Kiadás csoport</label>
              <select name="group" class="form-control" id="out_group">
                <option value="">-- válasszon --</option>
                {foreach from=$outgo_groups key=ik item=ig}
                <option value="{$ik}">{$ig}</option>
                {/foreach}
              </select>
            </div>
            <div class="form-group">
              <label for="out_holder">Pénz gyűjtő</label>
              <select name="holder" class="form-control" id="out_holder">
                <option value="">-- válasszon --</option>
                {foreach from=$cash_holders key=ik item=ig}
                <option value="{$ik}">{$ig}</option>
                {/foreach}
              </select>
            </div>
          </div>
          <div class="box-footer">
            <button type="submit" name="outgo" value="1" class="btn btn-warning pull-right">Kiadás rögzítése</button>
          </div>
        </div>
      </form>

      <div class="box">
        <div class="box-header with-border">
          <i class="fa fa-list"></i> <h3 class="box-title">Utoljára rögzített kiadások</h3>
        </div>
        <div class="box-body">
          <table class="table table-bordered table-striped">
            <thead>
              <tr>
                <th>ID</th>
                <th>Megjegyzés</th>
                <th>Összeg</th>
                <th>Dátum</th>
              </tr>
            </thead>
            <tbody>
              {foreach from=$trans_last_outgo item=i}
              <tr>
                <td>#{$i.id}</td>
                <td><strong>{$i.comment}</strong></td>
                <td>{$i.amount|number_format:0:",":"."} Ft</td>
                <td>{$i.trans_date|date_format:"%Y. %m. %d."}</td>
              </tr>
              {/foreach}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="row">
    <div class="col-md-12">
      <div class="box box-danger">
        <div class="box-header with-border">
          <i class="ion ion-cash"></i> <h3 class="box-title">{'Y'|date} pénzforgalmak</h3>
        </div>
        <div class="box-body">

        </div>
      </div>
    </div>
  </div>
</section>
