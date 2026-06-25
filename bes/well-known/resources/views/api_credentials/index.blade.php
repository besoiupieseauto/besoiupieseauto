@extends('layouts.mainappv1')

@section('title', 'API Credentials')
@section('content')
<div class="container">
    <h1>API Credentials</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('apicredentials.updateAll') }}" class="api-crendential">
        @csrf

        @php $chunks = array_chunk($services, 2, true); @endphp {{-- split services into groups of 2 --}}
        @foreach($chunks as $chunk)
            <div class="row">
                @foreach($chunk as $serviceName => $credentials)
                    @php
                        // ID unic pentru fiecare card (folosit la toggle)
                        $collapseId = 'service-' . $serviceName;
                    @endphp
                    <div class="col-md-6"> {{-- half width --}}
                        <div class="card mb-4">
                            <div class="card-header" data-target="#{{ $collapseId }}">
                                <strong>{{ ucfirst($serviceName) }}</strong>
                                <span class="pull-right glyphicon glyphicon-chevron-down toggle-icon"></span>
                            </div>
                            <div id="{{ $collapseId }}" class="card-body">
                                @foreach($credentials as $key => $credential)
                                    <div class="mb-3">
                                        <label class="form-label">{{ ucfirst(str_replace('_', ' ', $key)) }}</label>
                                        <input type="{{ strpos($key, 'offset_hours') !== false ? 'number' : 'text' }}"
                                               name="{{ $serviceName }}[{{ $key }}]" 
                                               value="{{ is_object($credential) ? $credential->data_value : $credential }}" 
                                               @if(strpos($key, 'offset_hours') !== false) step="1" @endif
                                               class="form-control">
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach

        <div class="row">
            <div class="col-md-12">
                <div class="card mb-4">
                    <div class="card-header" data-target="#whatsapp-templates">
                        <strong>WhatsApp message templates</strong>
                        <span class="pull-right glyphicon glyphicon-chevron-down toggle-icon"></span>
                    </div>
                    <div id="whatsapp-templates" class="card-body">
                        <p>
                            You can customize the WhatsApp messages sent from the system.
                            Use the variables shown under each template – they will be replaced automatically.
                        </p>

                        <div class="mb-3">
                            <label class="form-label">
                                Order ready for pickup (internal orders)
                            </label>
                            <textarea
                                name="whatsapp_templates[order_pickup]"
                                class="form-control"
                                rows="6"
                            >{{ old('whatsapp_templates.order_pickup', ($whatsappTemplates['order_pickup']->template ?? '') ) }}</textarea>
                            <small class="text-muted">
                                Available variables:
                                @{{client_name}}, @{{store_name}}, @{{total}}, @{{store_url}}
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                External order shipped (courier)
                            </label>
                            <textarea
                                name="whatsapp_templates[external_order_shipped]"
                                class="form-control"
                                rows="6"
                            >{{ old('whatsapp_templates.external_order_shipped', ($whatsappTemplates['external_order_shipped']->template ?? '') ) }}</textarea>
                            <small class="text-muted">
                                Available variables:
                                @{{client_name}}, @{{courier_name}}, @{{total}}, @{{awb}}, @{{awb_url}}
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                Wishlist/Offer message (saved carts)
                            </label>
                            <textarea
                                name="whatsapp_templates[wishlist_offer]"
                                class="form-control"
                                rows="8"
                            >{{ old('whatsapp_templates.wishlist_offer', ($whatsappTemplates['wishlist_offer']->template ?? '') ) }}</textarea>
                            <small class="text-muted">
                                Available variables:
                                @{{product_lines}}, @{{total}}, @{{vin}}
                                <br>
                                <strong>Note:</strong> @{{product_lines}} will be automatically replaced with the list of products from the cart.
                                @{{vin}} is replaced with the VIN saved on the offer (supplier_saved_carts.vin); leave the line out of the template if you do not use VIN.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card mb-4">
                    <div class="card-header" data-target="#sms-templates">
                        <strong>SMS message templates</strong>
                        <span class="pull-right glyphicon glyphicon-chevron-down toggle-icon"></span>
                    </div>
                    <div id="sms-templates" class="card-body">
                        <p>
                            You can customize the SMS messages sent from the system.
                            Use the variables shown under each template – they will be replaced automatically.
                        </p>

                        <div class="mb-3">
                            <label class="form-label">
                                Order ready for pickup - with total (internal orders)
                            </label>
                            <textarea
                                name="sms_templates[order_pickup_with_total]"
                                class="form-control"
                                rows="3"
                            >{{ old('sms_templates.order_pickup_with_total', ($smsTemplates['order_pickup_with_total']->template ?? '') ) }}</textarea>
                            <small class="text-muted">
                                Available variables:
                                @{{client_name}}, @{{store_name}}, @{{total}}, @{{store_url}}
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                Order ready for pickup - without total (internal orders, Avans status)
                            </label>
                            <textarea
                                name="sms_templates[order_pickup_no_total]"
                                class="form-control"
                                rows="3"
                            >{{ old('sms_templates.order_pickup_no_total', ($smsTemplates['order_pickup_no_total']->template ?? '') ) }}</textarea>
                            <small class="text-muted">
                                Available variables:
                                @{{client_name}}, @{{store_name}}, @{{store_url}}
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                External order shipped - Sameday
                            </label>
                            <textarea
                                name="sms_templates[external_order_sameday]"
                                class="form-control"
                                rows="3"
                            >{{ old('sms_templates.external_order_sameday', ($smsTemplates['external_order_sameday']->template ?? '') ) }}</textarea>
                            <small class="text-muted">
                                Available variables:
                                @{{total}}, @{{awb}}
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                External order shipped - FanCourier
                            </label>
                            <textarea
                                name="sms_templates[external_order_fancourier]"
                                class="form-control"
                                rows="3"
                            >{{ old('sms_templates.external_order_fancourier', ($smsTemplates['external_order_fancourier']->template ?? '') ) }}</textarea>
                            <small class="text-muted">
                                Available variables:
                                @{{total}}, @{{awb}}
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Save All</button>
    </form>
</div>

@section('page_scripts')
    // Face cardurile de pe pagina /api-credentials pliabile (collapsable)
    $(function () {
        var $form = $('.api-crendential');
        if (!$form.length) {
            return;
        }

        var $cards = $form.find('.card');

        // Hide all bodies by default
        $cards.find('.card-body').hide();
        // All icons start as "closed"
        $cards.find('.toggle-icon')
            .removeClass('glyphicon-chevron-up')
            .addClass('glyphicon-chevron-down');

        // Toggle la click pe header
        $cards.find('.card-header').css('cursor', 'pointer').on('click', function () {
            var $header = $(this);
            var $body   = $header.next('.card-body');
            var $icon   = $header.find('.toggle-icon');

            $body.slideToggle(150);
            $icon.toggleClass('glyphicon-chevron-down glyphicon-chevron-up');
        });
    });
@endsection
@endsection
