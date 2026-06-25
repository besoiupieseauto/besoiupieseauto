@extends('layouts.header_common_incasari')
@section('title', 'Incasari')

@section('content')
<!-- Main Content -->
<div class="container-fluid">
	<div class="panel panel-info">
		<div class="panel-heading">
			<h4><i class="fa fa-search"></i> Incasari</h4>
		</div>
		<div class="panel-body">
			<form class="form-horizontal" role="form" id="date_incasare">
				<div class="form-group">
					<div class="col-sm-2">
						<div class="input-group">
							<span class="input-group-addon">
								<a href="#">
									<span class="glyphicon glyphicon-chevron-left" onclick="obtine_date(-1)"></span>
								</a>
							</span>
							<input class="form-control" id="date" name="date" placeholder="DD/MM/YYYY" type="text" value="{{ $today }}" readonly/>
							<span class="input-group-addon">
								<a href="#">
									<span class="glyphicon glyphicon-chevron-right" onclick="obtine_date(1)"></span>
								</a>
							</span>
						</div>
					</div>
					<div class="col-sm-2">
						<select class="form-control" id="q_location">
							<option value="">Locație</option>
							<option value="1">TM</option>
							<option value="2">UTVIN</option>
						</select>
					</div>
					<div class="col-sm-2">
						<select class="form-control" id="q_method">
							<option value="">Metodă</option>
							<option value="3">Cash</option>
							<option value="6">Card</option>
							<option value="9">OP</option>
							<option value="7">FD</option>
							<option value="5">Return</option>
							<option value="4">Avans</option>
						</select>
					</div>
					<div class="col-sm-2">
						<button type="button" class="btn btn-primary" id="openPriceModal">
							<i class="fa fa-money"></i> Adauga Cheltuieli
						</button>
					</div>
					<div class="col-md-3">
						<span id="loader"></span>
					</div>
				</div>
			</form>
			
			<!-- Data will be loaded here -->
			<div class="outer_div"></div>
		</div>
	</div>
</div>

<div class="modal fade" id="priceModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title">Start Of Day Cash</h4>
      </div>
      <div class="modal-body">
        <form id="dailyPriceForm">
          <div class="form-group">
            <label for="daily_text">Text</label>
            <textarea class="form-control" id="daily_text" name="text" rows="3" required></textarea>
          </div>
          <div class="form-group">
            <label for="daily_price">Price (RON)</label>
            <input type="number" class="form-control" id="daily_price" name="price" required>
          </div>
          <div class="form-group">
            <label for="daily_location">Location</label>
            <select class="form-control" id="daily_location" name="location" required>
              <option value="TM">TM</option>
              <option value="UTVIN">UTVIN</option>
            </select>
          </div>
          <button type="submit" class="btn btn-success btn-sm">Save</button>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-default" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<script>
	$(document).ready(function() {
		// initialize datepicker with Romanian locale
		if($.fn.fdatepicker) {
			$('#date').fdatepicker({
				format: 'dd/mm/yyyy',
				language: 'ro'
			});
		}

		// When date changes manually
		$('#date, #q_location, #q_method').change(function() {
			loadIncomeData(1);
		});

		// Load initial data
		loadIncomeData(1);
		
		// Open modal
		$("#openPriceModal").click(function () {
			$("#priceModal").modal("show");
		});

		// Show edit mode
		$("#editPriceBtn").click(function () {
			$("#price_view_mode").hide();
			$("#price_edit_mode").show();
		});

		// Save edited amount
		$("#dailyPriceForm").submit(function (e) {
			e.preventDefault();

			const data = {
				text: $("#daily_text").val(),
				amount: $("#daily_price").val(),
				location: $("#daily_location").val(),
				_token: "{{ csrf_token() }}"
			};

			$.ajax({
				url: "{{ route('incasari.updateDailyPrice') }}",
				type: "POST",
				data: data,
				success: function () {
					alert("Data saved successfully!");
					$("#priceModal").modal("hide");
					// optionally reset form
					$("#dailyPriceForm")[0].reset();
					loadIncomeData(1);
				},
				error: function () {
					alert("Failed to save data.");
				}
			});
		});
	});

	// Function to increment or decrement date
	function obtine_date(id) {
		const data = $("#date").val();
		const data1 = data.split("/");
		const newdate = data1[1] + '/' + data1[0] + '/' + data1[2];
		const data_noua = new Date(newdate);
		let dd1,dd2,data2;

		if (id === 1) {
			data_noua.setDate(data_noua.getDate() + 1);
			dd1 = (data_noua.getMonth()+1) + '/' + data_noua.getDate() + '/' +  data_noua.getFullYear();
			data2 = dd1.split("/");
			dd2 = data2[1] + '/' + data2[0] + '/' + data2[2];

			$("#date").val(dd2);
		}
		else if (id===-1) {
			data_noua.setDate(data_noua.getDate() - 1);
			dd1=(data_noua.getMonth()+1) + '/' + data_noua.getDate() + '/' +  data_noua.getFullYear();
			data2=dd1.split("/");
			dd2=data2[1] + '/' + data2[0] + '/' + data2[2];

			$("#date").val(dd2);
		}

		loadIncomeData(1)
	}

	// Function to load income data
	function loadIncomeData(page) {
		const q_data = $("#date").val();
		const q_location = $('#q_location').val();
		const q_method = $('#q_method').val();

		$("#loader").html('<i class="fa fa-spinner fa-spin"></i>');

		$.ajax({
			url: '{{ route("incasari.data") }}',
			type: 'GET',
			data: { page: page, dt: q_data, q_location: q_location, q_method: q_method },
			success: function(data) {
				$(".outer_div").html(data).fadeIn('slow');
				$("#loader").html('');
			},
			error: function(xhr, status, error) {
				$("#loader").html('');
				console.error('Error loading data:', error);
				alert('Error loading data. Please try again later.');
			}
		});
	}
</script>
<style>
	.datepicker {
		top: 165px !important;
		bottom: auto !important;
		transform: translateY(0) !important;
		margin-top: 4px;
	}

	/* Total boxes styling to match your current UI */
	.total-wrapper {
		margin-top: 10px;
		margin-bottom: 10px;
	}

	.total-luna, .total-zi {
		display: inline-block;
		padding: 8px 15px;
		border-radius: 4px;
		font-weight: bold;
		color: white;
		margin-right: 10px;
	}

	.total-luna {
		background-color: #5bc0de;
	}

	.total-zi {
		background-color: #5cb85c !important;
	}

	.table-light > thead > tr > th,
	.table-light > tbody > tr > th,
	.table-light > tfoot > tr > th,
	.table-light > thead > tr > td,
	.table-light > tbody > tr > td,
	.table-light > tfoot > tr > td {
		border: 2px solid #031f24;
		border-right-width:0px;
		border-left-width:0px;
		overflow-x: auto;
	}

	.table-light > thead > tr > td.bottom,
	.table-light > tbody > tr > td.bottom,
	.table-light > tfoot > tr > td.bottom {
		padding: 8px;
		line-height: 1.42857143;
		vertical-align: top;
		border-top: 1px solid #ddd;
	}
</style>
@endsection