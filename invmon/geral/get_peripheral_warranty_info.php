<?php

/*                        Copyright 2020 Flávio Ribeiro

         This file is part of OCOMON.

         OCOMON is free software; you can redistribute it and/or modify
         it under the terms of the GNU General Public License as published by
         the Free Software Foundation; either version 3 of the License, or
         (at your option) any later version.

         OCOMON is distributed in the hope that it will be useful,
         but WITHOUT ANY WARRANTY; without even the implied warranty of
         MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
         GNU General Public License for more details.

         You should have received a copy of the GNU General Public License
         along with Foobar; if not, write to the Free Software
         Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
  */ session_start();
require_once __DIR__ . "/" . "../../includes/include_geral_new.inc.php";
require_once __DIR__ . "/" . "../../includes/classes/ConnectPDO.php";

use includes\classes\ConnectPDO;

$conn = ConnectPDO::getInstance();

$auth = new AuthNew($_SESSION['s_logado'], $_SESSION['s_nivel'], 2, 2);


$get = (isset($_GET) && !empty($_GET) ? $_GET : '');
if (empty($get)) {
    echo message('danger', 'Ooops!', TRANS('MSG_ERR_NOT_EXECUTE'), '', '', 1);
    return;
}
$peripheral_id = (isset($get['peripheral_id']) && !empty($get['peripheral_id']) ? noHtml($get['peripheral_id']) : '');

if (empty($peripheral_id)) {
    echo message('danger', 'Ooops!', TRANS('MSG_ERR_NOT_EXECUTE'), '', '', 1);
    return;
}

$query = $QRY["garantia_pieces"];
$query .= " AND e.estoq_cod = '{$peripheral_id}' 
			ORDER BY aquisicao";

$resultado = $conn->query($query);

$linhas = $resultado->rowCount();

if ($linhas) {
	$row = $resultado->fetch();

	$dias = date_diff_dias(date("Y-m-d"), $row['vencimento']);
	if ($dias >= 0) {
		$status = TRANS('UNDER_WARRANTY');
		$statusColor = 'green';
		if ($dias != 1) $s = TRANS('DAYS');
		else
			$s = ' dia';
		$expira = $dias . $s;
	} else {
		$status = TRANS('TXT_VANQUISHED_GUARANTEE');
		$statusColor = 'red';
		$expira = TRANS('TXT_DIED');
	}
}


?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" type="text/css" href="../../includes/css/estilos.css" />
	<link rel="stylesheet" type="text/css" href="../../includes/components/bootstrap/custom.css" />
	<link rel="stylesheet" type="text/css" href="../../includes/components/fontawesome/css/all.min.css" />

	<title>OcoMon&nbsp;<?= VERSAO; ?></title>
</head>

<body>
	
	<div class="container">
		<div id="idLoad" class="loading" style="display:none"></div>
	</div>

	<div id="divResult"></div>


	<div class="container-fluid">
		<h4 class="my-4"><i class="fas fa-hdd text-secondary"></i>&nbsp;<?= TRANS('DETACHED_COMPONENTS'); ?></h4>
		<div class="modal" id="modal" tabindex="-1" style="z-index:9001!important">
			<div class="modal-dialog modal-xl">
				<div class="modal-content">
					<div id="divDetails">
					</div>
				</div>
			</div>
		</div>

		<?php
		if (isset($_SESSION['flash']) && !empty($_SESSION['flash'])) {
			echo $_SESSION['flash'];
			$_SESSION['flash'] = '';
		}

		?>
		<h5 class="my-4"><?= TRANS('SUBTTL_CONTROL_GUARANTEE_FOR_MANUFACTURE'); ?></h5>
		<?php

		$i = 0;
		$j = 2;

		if ($linhas == 0) {

			echo message('info', 'Ooops!', TRANS('TXT_GUARANTEE_TEXT_1') . " <b>" . TRANS('TXT_NO') . "</b> " . TRANS('TXT_GUARANTEE_TEXT_2') . " " .
				"<hr>" . TRANS('TXT_GUARANTEE_TEXT_3'), '', '', 1);
		} else {
			print "<TABLE class='table' border='0' cellpadding='5' cellspacing='0' align='left' width='100%' >";

			print "<TR>";
			print "<TD><b>" . TRANS('COL_PARTNUMBER') . "</b></TD>";
			print "<TD><b>" . TRANS('LINK_GUARANT') . "</b></TD>";
			print "<TD><b>" . TRANS('COL_VENDOR') . "</b></TD>";
			print "<TD><b>" . TRANS('CONTACT') . "</b></TD>";
			print "<TD><b>" . TRANS('TXT_EXPIRATION') . "</b></TD>";
			print "<TD><b>" . TRANS('COL_REMAIN_TIME') . "</b></TD>";
			print "<TD><b>" . TRANS('COL_STATUS') . "</b></TD>";
			print "</tr>";

			print "<TR>";
			print "<TD>" . $row['estoq_partnumber'] . "</TD>";
			print "<TD>" . $row['meses'] . " meses</TD>";
			print "<TD>" . $row['fornecedor'] . "</TD>";
			print "<TD>" . $row['contato'] . "</TD>";
			print "<TD>" . $row['dia'] . "/" . $row['mes'] . "/" . $row['ano'] . "</TD>";
			print "<TD><font color='" . $statusColor . "'><b>" . $expira . "</b></font></TD>";
			print "<TD><font color='" . $statusColor . "'><b>" . $status . "</b></font></TD>";
			print "</tr>";
		}
		// print "<tr><td colspan='8' align='center'><input type='button' class='btn btn-secondary' value='".TRANS('LINK_CLOSE')."' onClick=\"javascript:self.close()\"</td></tr>";
		print "</table>";

		?>
		<div class="row w-100"></div><br />
		<div class="row">
			<div class="col-md-10 d-none d-md-block"></div>
			<div class="col-12 col-md-2">
				<button type="reset" class="btn btn-secondary btn-block close-or-return"><?= TRANS('BT_RETURN'); ?></button>
			</div>
		</div>
		
	</div>

	<script src="../../includes/javascript/funcoes-3.0.js"></script>
    <script src="../../includes/components/jquery/jquery.js"></script>
	<script src="../../includes/components/bootstrap/js/bootstrap.min.js"></script>
	<script type="text/javascript">
    
    $(() => {
        
        if ($('#table_lists').length > 0) {
            $('#table_lists').DataTable({
                paging: true,
                deferRender: true,
                
                "language": {
                    "url": "../../includes/components/datatables/datatables.pt-br.json"
                }
            });
        }
        
        closeOrReturn ();

    });

    function closeOrReturn (jumps = 1) {
        buttonValue ();
        $('.close-or-return').on('click', function(){
            if (isPopup()) {
                window.close();
            } else {
                window.history.back(jumps);
            }
        });
    }

    function buttonValue () {
        if (isPopup()) {
            $('.close-or-return').text('<?= TRANS('BT_CLOSE'); ?>');
        }
    }


    </script>

</body>

</html>