<section class="content-header">
  <h1>Gépház</h1>
</section>
<section class="content">
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
              <input type="date" name="date" class="form-control" id="in_date" placeholder="">
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

        </div>
      </div>
    </div>
  </div>
</section>
