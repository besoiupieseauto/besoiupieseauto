@extends('layouts.app')

@section('content')
<div class="container">
    <h2>SameDay AWB</h2>
    
    <div class="alert alert-success d-none" id="success-message"></div>
    <div class="alert alert-danger d-none" id="error-message"></div>
    
    <form id="awb-form">
        @csrf
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="service_id">Tip serviciu SameDay</label>
                    <select name="service_id" id="service_id" class="form-control" required>
                        <option value="">Select Service</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="pickup_point_id">Pickup Point</label>
                    <select name="pickup_point_id" id="pickup_point_id" class="form-control" required>
                        <option value="">Select Pickup Point</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="contact_person">Contact Person</label>
                    <input type="text" name="contact_person" id="contact_person" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="package_type">Package Type</label>
                    <select name="package_type" id="package_type" class="form-control" required>
                        <option value="1">Parcel</option>
                        <option value="2">Envelope</option>
                        <option value="3">Large Package</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="awb_payment">AWB Payment</label>
                    <select name="awb_payment" id="awb_payment" class="form-control" required>
                        <option value="1">Sender</option>
                        <option value="2">Recipient</option>
                    </select>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="form-group">
                    <label for="recipient_name">Recipient Name</label>
                    <input type="text" name="recipient_name" id="recipient_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="recipient_address">Recipient Address</label>
                    <input type="text" name="recipient_address" id="recipient_address" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="recipient_city">Recipient City</label>
                    <input type="text" name="recipient_city" id="recipient_city" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="recipient_county">Recipient County</label>
                    <input type="text" name="recipient_county" id="recipient_county" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="recipient_phone">Recipient Phone</label>
                    <input type="text" name="recipient_phone" id="recipient_phone" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="parcel_weight">Parcel Weight (kg)</label>
                    <input type="number" name="parcel_weight" id="parcel_weight" class="form-control" step="0.01" required>
                </div>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary">Create AWB</button>
    </form>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // सर्विसेज लोड करें
    $.ajax({
        url: "{{ route('sameday.services') }}",
        type: "GET",
        success: function(response) {
            if (response.success) {
                var services = response.services;
                var serviceSelect = $('#service_id');
                
                $.each(services, function(index, service) {
                    serviceSelect.append($('<option>', {
                        value: service.id,
                        text: service.name
                    }));
                });
            }
        },
        error: function(error) {
            $('#error-message').text('Failed to load services: ' + error.responseJSON.message).removeClass('d-none');
        }
    });
    
    // पिकअप पॉइंट्स लोड करें
    $.ajax({
        url: "{{ route('sameday.pickup-points') }}",
        type: "GET",
        success: function(response) {
            if (response.success) {
                var pickupPoints = response.pickupPoints;
                var pickupSelect = $('#pickup_point_id');
                
                $.each(pickupPoints, function(index, point) {
                    pickupSelect.append($('<option>', {
                        value: point.id,
                        text: point.city + ' - ' + point.address
                    }));
                });
            }
        },
        error: function(error) {
            $('#error-message').text('Failed to load pickup points: ' + error.responseJSON.message).removeClass('d-none');
        }
    });
    
    // फॉर्म सबमिशन
    $('#awb-form').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: "{{ route('sameday.create-awb') }}",
            type: "POST",
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    $('#success-message').text('AWB created successfully! AWB Number: ' + response.awb_number).removeClass('d-none');
                    $('#error-message').addClass('d-none');
                    $('#awb-form')[0].reset();
                }
            },
            error: function(error) {
                $('#error-message').text('Failed to create AWB: ' + error.responseJSON.message).removeClass('d-none');
                $('#success-message').addClass('d-none');
            }
        });
    });
});
</script>
@endsection