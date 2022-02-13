<?php
/* Copyright 2020 Flávio Ribeiro

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

if (!isset($_SESSION['s_logado']) || $_SESSION['s_logado'] == 0) {
	$_SESSION['session_expired'] = 1;
    echo "<script>top.window.location = '../../index.php'</script>";
	exit;
}

require_once __DIR__ . "/" . "../../includes/include_geral_new.inc.php";
require_once __DIR__ . "/" . "../../includes/classes/ConnectPDO.php";

use includes\classes\ConnectPDO;

$conn = ConnectPDO::getInstance();

$areaAdmin = 0;
if (isset($_SESSION['s_area_admin']) && $_SESSION['s_area_admin'] == '1' && $_SESSION['s_nivel'] != '1') {
	$areaAdmin = 1;
}
$auth = new AuthNew($_SESSION['s_logado'], $_SESSION['s_nivel'], 1);


$config = getConfig($conn);

if ($config['conf_updated_issues']) {
	redirect('types_of_issues_4.0.php');
	exit;
}

echo message('danger', TRANS('TXT_IMPORTANT'), TRANS('UPDATE_RELATION_AREAS_ISSUES') . "<hr>" . TRANS('CLICK_HERE_TO_UPDATE'), 'updateIssues', '', true);


$_SESSION['s_page_admin'] = $_SERVER['PHP_SELF'];

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" type="text/css" href="../../includes/css/estilos.css" />
	<link rel="stylesheet" type="text/css" href="../../includes/css/my_datatables.css" />
	<link rel="stylesheet" type="text/css" href="../../includes/css/switch_radio.css" />
	<link rel="stylesheet" type="text/css" href="../../includes/components/bootstrap/custom.css" />
	<link rel="stylesheet" type="text/css" href="../../includes/components/fontawesome/css/all.min.css" />
	<link rel="stylesheet" type="text/css" href="../../includes/components/datatables/datatables.min.css" />

	<title>OcoMon&nbsp;<?= VERSAO; ?></title>

	<style>
		li.except_areas {
			line-height: 1.5em;
		}
	</style>
</head>

<body>
	<?php
	// if ($areaAdmin) {
	// 	$auth->showHeader();
	// } else {
	// 	$auth->showHeader();
	// }
	?>
	
	<div class="container">
		<div id="idLoad" class="loading" style="display:none"></div>
	</div>

	<div id="divResult"></div>


	<div class="container-fluid">
		<h4 class="my-4"><i class="fas fa-exclamation-circle text-secondary"></i>&nbsp;<?= TRANS('PROBLEM_TYPES'); ?></h4>
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

		

		$query = "SELECT * FROM problemas as p 
                    LEFT JOIN sistemas as s on p.prob_area = s.sis_id 
                    LEFT JOIN sla_solucao as sl on sl.slas_cod = p.prob_sla 
                    LEFT JOIN prob_tipo_1 as pt1 on pt1.probt1_cod = p.prob_tipo_1 
                    LEFT JOIN prob_tipo_2 as pt2 on pt2.probt2_cod = p.prob_tipo_2 
                    LEFT JOIN prob_tipo_3 as pt3 on pt3.probt3_cod = p.prob_tipo_3 
                WHERE 1 = 1 ";

		if ($areaAdmin == 1) {
			$query .= "  AND p.prob_area = '" . $_SESSION['s_area'] . "' ";
		}
		
		$COD = (isset($_GET['cod']) && !empty($_GET['cod']) ? noHtml($_GET['cod']) : '' );
		if (!empty($COD)){
			$query .= " AND p.prob_id = '{$COD}' ";
		}

		$areaID = (isset($_GET['area']) && !empty($_GET['area']) ? noHtml($_GET['area']) : '' );
		if (!empty($areaID)){
			$query .= " AND p.prob_area = '{$areaID}' ";
		}
		
		
		$query .= " ORDER BY s.sistema, p.problema";
		$resultado = $conn->query($query);
		$registros = $resultado->rowCount();

		if ((!isset($_GET['action'])) && !isset($_POST['submit'])) {

		?>
			<!-- Modal -->
			<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
				<div class="modal-dialog">
					<div class="modal-content">
						<div class="modal-header bg-light">
							<h5 class="modal-title" id="exampleModalLabel"><i class="fas fa-exclamation-triangle text-secondary"></i>&nbsp;<?= TRANS('REMOVE'); ?></h5>
							<button type="button" class="close" data-dismiss="modal" aria-label="Close">
								<span aria-hidden="true">&times;</span>
							</button>
						</div>
						<div class="modal-body">
							<?= TRANS('CONFIRM_REMOVE'); ?> <span class="j_param_id"></span>?
						</div>
						<div class="modal-footer bg-light">
							<button type="button" class="btn btn-secondary" data-dismiss="modal"><?= TRANS('BT_CANCEL'); ?></button>
							<button type="button" id="deleteButton" class="btn"><?= TRANS('BT_OK'); ?></button>
						</div>
					</div>
				</div>
			</div>

			<button class="btn btn-sm btn-primary" id="idBtIncluir" name="new"><?= TRANS("ACT_NEW"); ?></button><br /><br />
			<?= TRANS('MANAGE_RELATED_ITENS'); ?>:&nbsp;<button class="btn btn-sm btn-secondary manage" data-location="cat_prob1" name="probtp1"><?= $config['conf_prob_tipo_1']; ?></button>
			<button class="btn btn-sm btn-secondary manage" data-location="cat_prob2" name="probtp2"><?= $config['conf_prob_tipo_2']; ?></button>
			<button class="btn btn-sm btn-secondary manage" data-location="cat_prob3" name="probtp3"><?= $config['conf_prob_tipo_3']; ?></button>
			<br /><br />
			<?php
			if ($registros == 0) {
				echo message('info', '', TRANS('NO_RECORDS_FOUND'), '', '', true);
			} else {

			?>
				<table id="table_lists" class="stripe hover order-column row-border" border="0" cellspacing="0" width="100%">

					<thead>
						<tr class="header">
							<td class="line issue_type"><?= TRANS('ISSUE_TYPE'); ?></td>
							<td class="line description" width="20%"><?= TRANS('DESCRIPTION'); ?></td>
							<td class="line area"><?= TRANS('AREA'); ?></td>
							<td class="line sla"><?= TRANS('COL_SLA'); ?></td>
							<td class="line tipo_1"><?= $config['conf_prob_tipo_1']; ?></td>
							<td class="line tipo_2"><?= $config['conf_prob_tipo_2']; ?></td>
							<td class="line tipo_3"><?= $config['conf_prob_tipo_3']; ?></td>
							<td class="line tipo_3"><?= TRANS('ACTIVE_O') ?></td>
							<td class="line editar"><?= TRANS('BT_EDIT'); ?></td>
							<td class="line remover"><?= TRANS('BT_REMOVE'); ?></td>
						</tr>
					</thead>
					<tbody>
						<?php

						foreach ($resultado->fetchall() as $row) {
							$active = ($row['prob_active'] ? '<span class="text-success"><i class="fas fa-check"></i></span>' : '');
							$inactive_class = (empty($active) ? 'table-danger' : '');
							$listAreas = "";

							if (count($hiddenInAreas = hiddenAreasByIssue($conn, $row['prob_id']))) {
								
								$listAreas = '<p class="text-danger font-weight-bold mt-2 mb-1">' . TRANS('EXCEPT') . ':</p>';
								foreach ($hiddenInAreas as $area) {
									$listAreas .= '<li class="except_areas text-danger" data-content="' . $area['area_id'] . '">' . $area['area_name'] ?? '' . '</li>';
								}
							}

							$td_class = (empty($listAreas) ? 'except_areas' : '');
							?>
							<tr class='<?= $inactive_class; ?>'>
								<td class="line"><?= $row['problema']; ?></td>
								<td class="line"><?= $row['prob_descricao']; ?></td>
								<td class="line <?= $td_class; ?>" data-content="<?= $row['sis_id']; ?>"><?= (!empty($row['sistema']) ? $row['sistema'] : TRANS('ALL') . $listAreas); ?></td>
								<td class="line"><?= ($row['slas_desc'] == '' ? TRANS('MSG_NOT_DEFINED') : $row['slas_desc']); ?></td>
								<td class="line"><?= ($row['probt1_desc'] == '' ? '<span class="text-danger"><i class="fas fa-ban"></i></span>' : $row['probt1_desc']); ?></td>
								<td class="line"><?= ($row['probt2_desc'] == '' ? '<span class="text-danger"><i class="fas fa-ban"></i></span>' : $row['probt2_desc']); ?></td>
								<td class="line"><?= ($row['probt3_desc'] == '' ? '<span class="text-danger"><i class="fas fa-ban"></i></span>' : $row['probt3_desc']); ?></td>
								<td class="line"><?= $active; ?></td>
								<td class="line"><button type="button" class="btn btn-secondary btn-sm" onclick="redirect('<?= $_SERVER['PHP_SELF']; ?>?action=edit&cod=<?= $row['prob_id']; ?>')"><?= TRANS('BT_EDIT'); ?></button></td>
								<td class="line"><button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteModal('<?= $row['prob_id']; ?>')"><?= TRANS('REMOVE'); ?></button></td>
							</tr>

							<?php
						}
						?>
					</tbody>
				</table>
			<?php
			}
		} else
		if ((isset($_GET['action'])  && ($_GET['action'] == "new")) && !isset($_POST['submit'])) {

			?>
			<h6><?= TRANS('NEW_RECORD'); ?></h6>
			<form method="post" action="<?= $_SERVER['PHP_SELF']; ?>" id="form">
				<?= csrf_input(); ?>
				<div class="form-group row my-4">
					<label for="problema" class="col-md-2 col-form-label col-form-label-sm text-md-right"><?= TRANS('ISSUE_TYPE'); ?></label>
					<div class="form-group col-md-10">
						<input type="text" class="form-control " id="problema" name="problema" required />
					</div>


					<label for="area" class="col-md-2 col-form-label col-form-label-sm text-md-right"><?= TRANS('AREA'); ?></label>
					<div class="form-group col-md-10">
						<select class="form-control" name="area" id="area" required>
							<option value="-1" selected><?= TRANS('ALL'); ?></option>
							<?php
							if ($areaAdmin) {
								$sql = "SELECT sis_id, sistema FROM sistemas WHERE sis_status NOT IN (0) AND sis_atende = 1 AND sis_id = " . $_SESSION['s_area'] . " ORDER BY sistema ";
							} else {
								$sql = "SELECT sis_id, sistema FROM sistemas WHERE sis_status NOT IN (0) AND sis_atende = 1 ORDER BY sistema ";
							}
							$res = $conn->query($sql);
							foreach ($res->fetchall() as $rowArea) {
							?>
								<option value='<?= $rowArea['sis_id']; ?>'><?= $rowArea['sistema']; ?></option>
							<?php
							}
							?>
						</select>
					</div>

					<label for="sla" class="col-md-2 col-form-label col-form-label-sm text-md-right"><?= TRANS('COL_SLA'); ?></label>
					<div class="form-group col-md-10">
						<select class="form-control" name="sla" id="sla" required>
							<option value="-1" selected><?= TRANS('SEL_SLA'); ?></option>
							<?php
							$sql = "SELECT * FROM sla_solucao ORDER BY slas_tempo";
							$resSLA = $conn->query($sql);
							foreach ($resSLA->fetchall() as $rowSLA) {
								$inHours = "";
								if (!empty($rowSLA['slas_tempo']) && $rowSLA['slas_tempo'] > 60)
									$inHours = round($rowSLA['slas_tempo'] / 60, 2) . " " . TRANS('FILTERED_HOURS'); else
									$inHours = TRANS('FILTERED_TIME');
								?>
								<option value="<?= $rowSLA['slas_cod']; ?>"><?= $rowSLA['slas_desc'] . " (" . $inHours . ")"; ?></option>
								<?php
							}
							?>
						</select>
					</div>

					<label for="tipo_1" class="col-md-2 col-form-label col-form-label-sm text-md-right"><?= $config['conf_prob_tipo_1']; ?></label>
					<div class="form-group col-md-10">
						<div class="input-group">
							<select class="form-control" name="tipo_1" id="tipo_1" required>
								<option value="-1" selected><?= TRANS('SEL_TYPE'); ?></option>
								<?php
								$sql = "SELECT * FROM prob_tipo_1 ORDER BY probt1_desc";
								$resType1 = $conn->query($sql);
								foreach ($resType1->fetchall() as $rowType1) {
								?>
									<option value="<?= $rowType1['probt1_cod']; ?>"><?= $rowType1['probt1_desc']; ?></option>
								<?php
								}
								?>
							</select>
							<div class="input-group-append">
								<div class="input-group-text manage_categories" data-location="cat_prob1" data-params="action=new" title="<?= TRANS('ADD_CATEGORY'); ?>" data-placeholder="<?= TRANS('ADD_CATEGORY'); ?>" data-toggle="popover" data-placement="top" data-trigger="hover">
									<i class="fas fa-plus"></i>
								</div>
							</div>
						</div>
					</div>

					<label for="tipo_2" class="col-md-2 col-form-label col-form-label-sm text-md-right"><?= $config['conf_prob_tipo_2']; ?></label>
					<div class="form-group col-md-10">
						<div class="input-group">
							<select class="form-control" name="tipo_2" id="tipo_2" >
								<option value="-1" selected><?= TRANS('SEL_TYPE'); ?></option>
								<?php
								$sql = "SELECT * FROM prob_tipo_2 ORDER BY probt2_desc";
								$resType2 = $conn->query($sql);
								foreach ($resType2->fetchall() as $rowType2) {
								?>
									<option value="<?= $rowType2['probt2_cod']; ?>"><?= $rowType2['probt2_desc']; ?></option>
								<?php
								}
								?>
							</select>
							<div class="input-group-append">
								<div class="input-group-text manage_categories" data-location="cat_prob2" data-params="action=new" title="<?= TRANS('ADD_CATEGORY'); ?>" data-placeholder="<?= TRANS('ADD_CATEGORY'); ?>" data-toggle="popover" data-placement="top" data-trigger="hover">
									<i class="fas fa-plus"></i>
								</div>
							</div>
						</div>
					</div>

					<label for="tipo_3" class="col-md-2 col-form-label col-form-label-sm text-md-right"><?= $config['conf_prob_tipo_3']; ?></label>
					<div class="form-group col-md-10">
						<div class="input-group">
							<select class="form-control" name="tipo_3" id="tipo_3" >
								<option value="-1" selected><?= TRANS('SEL_TYPE'); ?></option>
								<?php
								$sql = "SELECT * FROM prob_tipo_3 ORDER BY probt3_desc";
								$resType3 = $conn->query($sql);
								foreach ($resType3->fetchall() as $rowType3) {
								?>
									<option value="<?= $rowType3['probt3_cod']; ?>"><?= $rowType3['probt3_desc']; ?></option>
								<?php
								}
								?>
							</select>
							<div class="input-group-append">
								<div class="input-group-text manage_categories" data-location="cat_prob3" data-params="action=new" title="<?= TRANS('ADD_CATEGORY'); ?>" data-placeholder="<?= TRANS('ADD_CATEGORY'); ?>" data-toggle="popover" data-placement="top" data-trigger="hover">
									<i class="fas fa-plus"></i>
								</div>
							</div>
						</div>
					</div>


					<label for="descricao" class="col-md-2 col-form-label col-form-label-sm text-md-right"><?= TRANS('DESCRIPTION'); ?></label>
					<div class="form-group col-md-10">
						<textarea class="form-control" id="descricao" name="descricao" rows="4"></textarea>
						<small class="form-text text-muted">
							<?= TRANS('TYPE_OF_ISSUE_DESCRIPTION_HELPER'); ?>.
						</small>
					</div>

					<div class="row w-100"></div>
					<div class="form-group col-md-8 d-none d-md-block">
					</div>
					<div class="form-group col-12 col-md-2 ">

						<input type="hidden" name="bypassCsrf" id="bypassCsrf" value="0">
						<input type="hidden" name="cat1_selected" value="" id="cat1_selected" />
						<input type="hidden" name="cat2_selected" value="" id="cat2_selected" />
						<input type="hidden" name="cat3_selected" value="" id="cat3_selected" />
						<input type="hidden" name="action" id="action" value="new">
						<button type="submit" id="idSubmit" name="submit" class="btn btn-primary btn-block"><?= TRANS('BT_OK'); ?></button>
					</div>
					<div class="form-group col-12 col-md-2">
						<button type="reset" class="btn btn-secondary btn-block" onClick="parent.history.back();"><?= TRANS('BT_CANCEL'); ?></button>
					</div>


				</div>
			</form>
		<?php
		} else

		if ((isset($_GET['action']) && $_GET['action'] == "edit") && empty($_POST['submit'])) {

			$row = $resultado->fetch();
		?>
			<h6><?= TRANS('BT_EDIT'); ?></h6>
			<form method="post" action="<?= $_SERVER['PHP_SELF']; ?>" id="form">
				<?= csrf_input(); ?>
				<div class="form-group row my-4">
					<label for="problema" class="col-md-2 col-form-label col-form-label-sm text-md-right"><?= TRANS('ISSUE_TYPE'); ?></label>
					<div class="form-group col-md-10">
						<input type="text" class="form-control " id="problema" name="problema" value="<?= $row['problema']; ?>" required />
					</div>


					<label for="area" class="col-md-2 col-form-label col-form-label-sm text-md-right"><?= TRANS('AREA'); ?></label>
					<div class="form-group col-md-10">
						<select class="form-control" name="area" id="area" required>
							<option value="-1"><?= TRANS('ALL'); ?></option>
							<?php
							if ($areaAdmin) {
								$sql = "SELECT sis_id, sistema FROM sistemas WHERE sis_status NOT IN (0) AND sis_atende = 1 AND sis_id = " . $_SESSION['s_area'] . " ORDER BY sistema ";
							} else {
								$sql = "SELECT sis_id, sistema FROM sistemas WHERE sis_status NOT IN (0) AND sis_atende = 1 ORDER BY sistema ";
							}
							$res = $conn->query($sql);
							foreach ($res->fetchall() as $rowArea) {
							?>
								<option value='<?= $rowArea['sis_id']; ?>' <?= ($row['sis_id'] == $rowArea['sis_id'] ? 'selected' : ''); ?>><?= $rowArea['sistema']; ?></option>
							<?php
							}
							?>
						</select>
					</div>

					<label for="sla" class="col-md-2 col-form-label col-form-label-sm text-md-right"><?= TRANS('COL_SLA'); ?></label>
					<div class="form-group col-md-10">
						<select class="form-control" name="sla" id="sla" required>
							<option value="-1" selected><?= TRANS('SEL_SLA'); ?></option>
							<?php
							$sql = "SELECT * FROM sla_solucao ORDER BY slas_tempo";
							$resSLA = $conn->query($sql);
							foreach ($resSLA->fetchall() as $rowSLA) {
								$inHours = "";
								if (!empty($rowSLA['slas_tempo']) && $rowSLA['slas_tempo'] > 60)
									$inHours = round($rowSLA['slas_tempo'] / 60, 2) . " " . TRANS('FILTERED_HOURS'); else
									$inHours = TRANS('FILTERED_TIME');
								?>
								<option value="<?= $rowSLA['slas_cod']; ?>" <?= ($row['slas_cod'] == $rowSLA['slas_cod'] ? 'selected' : ''); ?>><?= $rowSLA['slas_desc'] . " (" . $inHours . ")"; ?></option>
								<?php
							}
							?>
						</select>
					</div>

					<label for="tipo_1" class="col-md-2 col-form-label col-form-label-sm text-md-right"><?= $config['conf_prob_tipo_1']; ?></label>
					<div class="form-group col-md-10">
						<div class="input-group">
							<select class="form-control" name="tipo_1" id="tipo_1">
								<option value="-1" selected><?= TRANS('SEL_TYPE'); ?></option>
								<?php
								$sql = "SELECT * FROM prob_tipo_1 ORDER BY probt1_desc";
								$resType1 = $conn->query($sql);
								foreach ($resType1->fetchall() as $rowType1) {
								?>
									<option value="<?= $rowType1['probt1_cod']; ?>" <?= ($row['prob_tipo_1'] == $rowType1['probt1_cod'] ? 'selected' : ''); ?>><?= $rowType1['probt1_desc']; ?></option>
								<?php
								}
								?>
							</select>
							<input type="hidden" name="cat1_selected" value="<?= $row['prob_tipo_1']; ?>" id="cat1_selected" />
							<div class="input-group-append">
								<div class="input-group-text manage_categories" data-location="cat_prob1" data-params="action=new" title="<?= TRANS('ADD_CATEGORY'); ?>" data-placeholder="<?= TRANS('ADD_CATEGORY'); ?>" data-toggle="popover" data-placement="top" data-trigger="hover">
									<i class="fas fa-plus"></i>
								</div>
							</div>
						</div>
					</div>

					<label for="tipo_2" class="col-md-2 col-form-label col-form-label-sm text-md-right"><?= $config['conf_prob_tipo_2']; ?></label>
					<div class="form-group col-md-10">
						<div class="input-group">
							<select class="form-control" name="tipo_2" id="tipo_2" >
								<option value="-1" selected><?= TRANS('SEL_TYPE'); ?></option>
								<?php
								$sql = "SELECT * FROM prob_tipo_2 ORDER BY probt2_desc";
								$resType2 = $conn->query($sql);
								foreach ($resType2->fetchall() as $rowType2) {
								?>
									<option value="<?= $rowType2['probt2_cod']; ?>" <?= ($row['prob_tipo_2'] == $rowType2['probt2_cod'] ? 'selected' : ''); ?>><?= $rowType2['probt2_desc']; ?></option>
								<?php
								}
								?>
							</select>
							<input type="hidden" name="cat2_selected" value="<?= $row['prob_tipo_2']; ?>" id="cat2_selected" />
							<div class="input-group-append">
								<div class="input-group-text manage_categories" data-location="cat_prob2" data-params="action=new" title="<?= TRANS('ADD_CATEGORY'); ?>" data-placeholder="<?= TRANS('ADD_CATEGORY'); ?>" data-toggle="popover" data-placement="top" data-trigger="hover">
									<i class="fas fa-plus"></i>
								</div>
							</div>
						</div>
					</div>

					<label for="tipo_3" class="col-md-2 col-form-label col-form-label-sm text-md-right"><?= $config['conf_prob_tipo_3']; ?></label>
					<div class="form-group col-md-10">
						<div class="input-group">
							<select class="form-control" name="tipo_3" id="tipo_3" required>
								<option value="-1" selected><?= TRANS('SEL_TYPE'); ?></option>
								<?php
								$sql = "SELECT * FROM prob_tipo_3 ORDER BY probt3_desc";
								$resType3 = $conn->query($sql);
								foreach ($resType3->fetchall() as $rowType3) {
								?>
									<option value="<?= $rowType3['probt3_cod']; ?>" <?= ($row['prob_tipo_3'] == $rowType3['probt3_cod'] ? 'selected' : ''); ?>><?= $rowType3['probt3_desc']; ?></option>
								<?php
								}
								?>
							</select>
							<input type="hidden" name="cat3_selected" value="<?= $row['prob_tipo_3']; ?>" id="cat3_selected" />
							<div class="input-group-append">
								<div class="input-group-text manage_categories" data-location="cat_prob3" data-params="action=new" title="<?= TRANS('ADD_CATEGORY'); ?>" data-placeholder="<?= TRANS('ADD_CATEGORY'); ?>" data-toggle="popover" data-placement="top" data-trigger="hover">
									<i class="fas fa-plus"></i>
								</div>
							</div>
						</div>
					</div>


					<?php
					/* Ver sobre a barra de formatação*/
					$texto1 = str_replace("\r", "\n", $row['prob_descricao']);
					$texto1 = str_replace("\n", "", $texto1);
					?>
					<label for="descricao" class="col-md-2 col-form-label col-form-label-sm text-md-right"><?= TRANS('DESCRIPTION'); ?></label>
					<div class="form-group col-md-10">
						<textarea class="form-control" id="descricao" name="descricao" rows="4"><?= $row['prob_descricao']; ?></textarea>
						<small class="form-text text-muted">
							<?= TRANS('TYPE_OF_ISSUE_DESCRIPTION_HELPER'); ?>.
						</small>
					</div>


					<label class="col-md-2 col-form-label text-md-right"><?= firstLetterUp(TRANS('ACTIVE_O')); ?></label>
					<div class="form-group col-md-4 ">
						<div class="switch-field">
							<?php
							$yesChecked = ($row['prob_active'] == 1 ? "checked" : "");
							$noChecked = (!($row['prob_active'] == 1) ? "checked" : "");
							?>
							<input type="radio" id="prob_active" name="prob_active" value="yes" <?= $yesChecked; ?> />
							<label for="prob_active"><?= TRANS('YES'); ?></label>
							<input type="radio" id="prob_active_no" name="prob_active" value="no" <?= $noChecked; ?> />
							<label for="prob_active_no"><?= TRANS('NOT'); ?></label>
						</div>
					</div>


					<div class="row w-100"></div>
					<div class="form-group col-md-8 d-none d-md-block">
					</div>
					<div class="form-group col-12 col-md-2 ">
						<input type="hidden" name="cod" value="<?= $COD; ?>">
						<input type="hidden" name="action" id="action" value="edit">
						<button type="submit" id="idSubmit" name="submit" value="edit" class="btn btn-primary btn-block"><?= TRANS('BT_OK'); ?></button>
					</div>
					<div class="form-group col-12 col-md-2">
						<button type="reset" class="btn btn-secondary btn-block" onClick="parent.history.back();"><?= TRANS('BT_CANCEL'); ?></button>
					</div>

				</div>
			</form>
		<?php
		}
		?>
	</div>

	<script src="../../includes/javascript/funcoes-3.0.js"></script>
	<script src="../../includes/components/jquery/jquery.js"></script>
	<!-- <script type="text/javascript" src="../../includes/components/jquery/jquery-ui-1.12.1/jquery-ui.js"></script> -->
	<script src="../../includes/components/bootstrap/js/bootstrap.min.js"></script>
	<script type="text/javascript" charset="utf8" src="../../includes/components/datatables/datatables.js"></script>
	<script type="text/javascript">
		$(function() {

			
			$('#updateIssues').on('click', function(){
				var url = './update_issues_areas.php';
				$(location).prop('href', url);
				return false;
			}).css({
				cursor: 'pointer'
			});
			
			$('#table_lists').DataTable({
				paging: true,
				deferRender: true,
				columnDefs: [{
					searchable: false,
					orderable: false,
					targets: ['editar', 'remover']
				}],
				language: {
					"url": "../../includes/components/datatables/datatables.pt-br.json"
					// "url": "https://cdn.datatables.net/plug-ins/1.10.25/i18n/Portuguese-Brasil.json"
				}
			});


			$('.except_areas').on('click', function() {
				var app = 'issues_by_area.php';
				$(location).prop('href', app + '?area=' + $(this).attr('data-content'));
				return false;
			}).css('cursor', 'pointer');

			$('.manage').on('click', function() {
				loadInModal($(this).attr('data-location'));
			});

			
			loadCat1();
			loadCat2();
			loadCat3();
			
			$('.manage_categories').on('click', function() {
                loadInPopup($(this).attr('data-location'), $(this).attr('data-params'));
			});
			
			
			



			$('#idSubmit').on('click', function(e) {
				e.preventDefault();
				var loading = $(".loading");
				$(document).ajaxStart(function() {
					loading.show();
				});
				$(document).ajaxStop(function() {
					loading.hide();
				});

				$("#idSubmit").prop("disabled", true);
				$.ajax({
					url: './issues_types_process.php',
					method: 'POST',
					data: $('#form').serialize(),
					dataType: 'json',
				}).done(function(response) {

					if (!response.success) {
						$('#divResult').html(response.message);
						$('input, select, textarea').removeClass('is-invalid');
						if (response.field_id != "") {
							$('#' + response.field_id).focus().addClass('is-invalid');
						}
						$("#idSubmit").prop("disabled", false);
					} else {
						$('#divResult').html('');
						$('input, select, textarea').removeClass('is-invalid');
						$("#idSubmit").prop("disabled", false);
						var url = '<?= $_SERVER['PHP_SELF'] ?>';
						$(location).prop('href', url);
						return false;
					}
				});
				return false;
			});

			$('#idBtIncluir').on("click", function() {
				$('#idLoad').css('display', 'block');
				var url = '<?= $_SERVER['PHP_SELF'] ?>?action=new';
				$(location).prop('href', url);
			});

			$('#bt-cancel').on('click', function() {
				var url = '<?= $_SERVER['PHP_SELF'] ?>';
				$(location).prop('href', url);
			});
		});

		function bypassCsrf() {
			$('#bypassCsrf').val(1);
		}

		function loadCat1(selected_id = '') {
			$.ajax({
				url: './get_issue_categories.php',
				method: 'POST',
				data: {cat_type: 1}, 
				dataType: 'json',
			}).done(function(response) {
				$('#tipo_1').empty().append('<option value=""><?= TRANS('SEL_TYPE'); ?></option>');
				for (var i in response) {

					var option = '<option value="' + response[i].probt1_cod + '">' + response[i].probt1_desc + '</option>';
					$('#tipo_1').append(option);

					if (selected_id !== '') {
						$('#tipo_1').val(selected_id).change();
					} else
					if ($('#cat1_selected').val() != '') {
						$('#tipo_1').val($('#cat1_selected').val()).change();
					}
				}
			});
		}

		function loadCat2(selected_id = '') {
			$.ajax({
				url: './get_issue_categories.php',
				method: 'POST',
				data: {cat_type: 2},
				dataType: 'json',
			}).done(function(response) {
				$('#tipo_2').empty().append('<option value=""><?= TRANS('SEL_TYPE'); ?></option>');
				for (var i in response) {

					var option = '<option value="' + response[i].probt2_cod + '">' + response[i].probt2_desc + '</option>';
					$('#tipo_2').append(option);

					if (selected_id !== '') {
						$('#tipo_2').val(selected_id).change();
					} else
					if ($('#cat2_selected').val() != '') {
						$('#tipo_2').val($('#cat2_selected').val()).change();
					}
				}
			});
		}

		function loadCat3(selected_id = '') {
			$.ajax({
				url: './get_issue_categories.php',
				method: 'POST',
				data: {cat_type: 3},
				dataType: 'json',
			}).done(function(response) {
				$('#tipo_3').empty().append('<option value=""><?= TRANS('SEL_TYPE'); ?></option>');
				for (var i in response) {

					var option = '<option value="' + response[i].probt3_cod + '">' + response[i].probt3_desc + '</option>';
					$('#tipo_3').append(option);

					if (selected_id !== '') {
						$('#tipo_3').val(selected_id).change();
					} else
					if ($('#cat3_selected').val() != '') {
						$('#tipo_3').val($('#cat3_selected').val()).change();
					}
				}
			});
		}

		function confirmDeleteModal(id) {
			$('#deleteModal').modal();
			$('#deleteButton').html('<a class="btn btn-danger" onclick="deleteData(' + id + ')"><?= TRANS('REMOVE'); ?></a>');
		}

		function deleteData(id) {

			var loading = $(".loading");
			$(document).ajaxStart(function() {
				loading.show();
			});
			$(document).ajaxStop(function() {
				loading.hide();
			});

			$.ajax({
				url: './issues_types_process.php',
				method: 'POST',
				data: {
					cod: id,
					action: 'delete'
				},
				dataType: 'json',
			}).done(function(response) {
				var url = '<?= $_SERVER['PHP_SELF'] ?>';
				$(location).prop('href', url);
				return false;
			});
			return false;
			// $('#deleteModal').modal('hide'); // now close modal
		}

		function loadInModal(pageBase) {
			let url = pageBase + '.php';
			$(location).prop('href', url);
			// $("#divDetails").load(url);
			// $('#modal').modal();
		}

		function loadInPopup(pageBase, params) {
            let url = pageBase + '.php?' + params;
            x = window.open(url,'','dependent=yes,width=800,scrollbars=yes,statusbar=no,resizable=yes');
		    x.moveTo(10,10);
		}
	</script>
</body>

</html>