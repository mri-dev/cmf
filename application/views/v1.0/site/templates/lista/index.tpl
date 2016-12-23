<section class="content-header">
  <h1>Tranzakciók listája</h1>
</section>
<section class="content">
  <div class="box">
    <div class="box-body">
      <table class="table dt table-bordered table-striped">
        <thead>
          <tr>
            <th>ID</th>
            <th>Dátum</th>
            <th>Megjegyzés</th>
            <th>Összeg</th>
            <th>Forgalom</th>
            <th>Időbélyeg</th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$list item=i}
          <tr>
            <td>#{$i.id}</td>
            <td>{$i.trans_date|date_format:"%Y. %m. %d."}</td>
            <td><strong>{$i.comment}</strong></td>
            <td>{$i.amount|number_format:0:",":"."} Ft</td>
            <td><span class="label label-{if $i.forgalom =='Bevétel'}success{else}warning{/if}">{$i.forgalom|ucfirst}</span></td>
            <td>{$i.register_date|date_format:"%Y. %m. %d.  %H:%S"}</td>
          </tr>
          {/foreach}
        </tbody>
      </table>
    </div>
  </div>
</section>
