<!--Testing start-->
       <div class="row">
            <div class="col-md-12">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#myModal">
                    <i class="glyphicon glyphicon-plus"></i> Adauga Produse
                </button>
            </div>
        </div>
        
        <div class="row" style="margin-top: 20px;">
            <div class="col-md-12">
                <!-- इनवॉइस आइटम्स कंटेनर -->
                <div id="invoice-items-container">
                    <div id="invoice-items">
                        <!-- यहां इनवॉइस आइटम्स लोड होंगे -->
                        <table class="table table-bordered">
                            <thead>
                                <tr class="warning">
                                    <th>Nr. Crt.</th>
                                    <th>PRODUS</th>
                                    <th>UM</th>
                                    <th>CANT.</th>
                                    <th>PRET UNIT.</th>
                                    <th>VALOARE.</th>
                                    <th>TVA</th>
                                    <th>Cota TVA</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="9" class="text-center">No items found</td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="text-right"><strong>SUBTOTAL</strong></td>
                                    <td>0.00</td>
                                    <td>0.00</td>
                                    <td colspan="2"></td>
                                </tr>
                                <tr>
                                    <td colspan="7" class="text-right"><strong>TOTAL</strong></td>
                                    <td colspan="2">0.00 lei</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- सबमिट बटन -->
        <div class="row" style="margin-top: 20px;">
            <div class="col-md-12">
                <button type="button" class="btn btn-success" id="saveInvoice">
                    <i class="glyphicon glyphicon-floppy-disk"></i> Salvare Factura
                </button>
            </div>
        </div>
    </div>

    <!-- प्रोडक्ट सर्च मॉडल -->
    <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="myModalLabel">Cauta produs</h4>
                </div>
                <div class="modal-body">
                    <form class="form-horizontal">
                        <div class="form-group">
                            <div class="col-sm-6">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="q" placeholder="Cauta produs" onkeyup="load(1)">
                                    <span class="input-group-addon">
                                        <a href="javascript:void(0);">
                                            <span class="glyphicon glyphicon-search" onclick="load(1);"></span>
                                        </a>
                                    </span>
                                </div>
                            </div>
                            <button type="button" class="btn btn-default" data-toggle="modal" data-target="#produs_nou">
                                <span class="glyphicon glyphicon-plus"></span> Produs nou
                            </button>
                        </div>
                        <div id="loader" style="position: absolute; text-align: center; top: 55px; width: 100%; display: none;">
                            <img src="{{ asset('images/loader.gif') }}" alt="Loading...">
                        </div>
                        <div class="outer_div">
                            <!-- यहां डाइनामिक प्रोडक्ट्स लोड होंगे -->
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Inchide</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // CSRF टोकन सेटअप
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        // प्रोडक्ट्स लोड करने का फंक्शन
        function load(page = 1) {
            let query = $('#q').val();
            $('#loader').show();
            
            $.ajax({
                url: '/search-products',
                type: 'GET',
                data: {
                    query: query,
                    page: page
                },
                success: function(data) {
                    let html = '';
                    
                    html += '<div class="table-responsive">';
                    html += '<table class="table table-bordered" style="width:100%">';
                    html += '<tr class="warning">';
                    html += '<th>Produs</th>';
                    html += '<th><span class="pull-right">Cant.</span></th>';
                    html += '<th><span class="pull-right">Pret</span></th>';
                    html += '<th class="text-center" style="width: 36px;">Adauga</th>';
                    html += '</tr>';
                    
                    if (data.products.length > 0) {
                        $.each(data.products, function(index, product) {
                            html += '<tr>';
                            html += '<td>' + product.denumire + '</td>';
                            html += '<td class="col-xs-1">';
                            html += '<div class="pull-right">';
                            html += '<input type="text" class="form-control" style="text-align:right" id="cantitate_' + product.idprodus + '" value="1">';
                            html += '</div></td>';
                            html += '<td class="col-xs-2"><div class="pull-right">';
                            html += '<input type="text" class="form-control" style="text-align:right" id="pret_unitar_' + product.idprodus + '" value="' + product.pret + '">';
                            html += '</div></td>';
                            html += '<td class="text-center"><a class="btn btn-info" href="javascript:void(0);" onclick="adauga(\'' + product.idprodus + '\')"><i class="glyphicon glyphicon-plus"></i></a></td>';
                            html += '</tr>';
                        });
                    } else {
                        html += '<tr><td colspan="4" class="text-center">Nu s-au gasit produse</td></tr>';
                    }
                    
                    // पेजिनेशन जोड़ें
                    html += '<tr>';
                    html += '<td colspan="4"><span class="pull-right"><ul class="pagination pagination-large">';
                    
                    // पिछला बटन
                    if (data.pagination.current_page > 1) {
                        html += '<li><span><a href="javascript:void(0);" onclick="load(' + (data.pagination.current_page - 1) + ')">‹ Prev</a></span></li>';
                    } else {
                        html += '<li class="disabled"><span><a>‹ Prev</a></span></li>';
                    }
                    
                    // पेज नंबर्स
                    let startPage = Math.max(1, data.pagination.current_page - 2);
                    let endPage = Math.min(data.pagination.total_pages, data.pagination.current_page + 2);
                    
                    for (let i = startPage; i <= endPage; i++) {
                        if (i == data.pagination.current_page) {
                            html += '<li class="active"><a>' + i + '</a></li>';
                        } else {
                            html += '<li><a href="javascript:void(0);" onclick="load(' + i + ')">' + i + '</a></li>';
                        }
                    }
                    
                    // एलिप्सिस
                    if (endPage < data.pagination.total_pages) {
                        html += '<li><a>...</a></li>';
                        html += '<li><a href="javascript:void(0);" onclick="load(' + data.pagination.total_pages + ')">' + data.pagination.total_pages + '</a></li>';
                    }
                    
                    // अगला बटन
                    if (data.pagination.current_page < data.pagination.total_pages) {
                        html += '<li><span><a href="javascript:void(0);" onclick="load(' + (data.pagination.current_page + 1) + ')">Next ›</a></span></li>';
                    } else {
                        html += '<li class="disabled"><span><a>Next ›</a></span></li>';
                    }
                    
                    html += '</ul></span></td>';
                    html += '</tr>';
                    
                    // ट्रांसपोर्ट चार्जेज को एक फिक्स्ड ऑप्शन के रूप में जोड़ें
                    html += '<tr>';
                    html += '<td>CHELTUIELI TRANSPORT</td>';
                    html += '<td class="col-xs-1">';
                    html += '<div class="pull-right">';
                    html += '<input type="text" class="form-control" style="text-align:right" id="cantitate_32066" value="1">';
                    html += '</div></td>';
                    html += '<td class="col-xs-2"><div class="pull-right">';
                    html += '<input type="text" class="form-control" style="text-align:right" id="pret_unitar_32066" value="30">';
                    html += '</div></td>';
                    html += '<td class="text-center"><a class="btn btn-info" href="javascript:void(0);" onclick="adauga(\'32066\')"><i class="glyphicon glyphicon-plus"></i></a></td>';
                    html += '</tr>';
                    
                    html += '</table>';
                    html += '</div>';
                    
                    $('.outer_div').html(html);
                    $('#loader').hide();
                },
                error: function() {
                    $('#loader').hide();
                    alert('Error loading products');
                }
            });
        }

        // प्रोडक्ट को इनवॉइस में जोड़ने का फंक्शन
        function adauga(idprodus) {
            let cantitate = $('#cantitate_' + idprodus).val();
            let pret = $('#pret_unitar_' + idprodus).val();
            
            $.ajax({
                url: '/add-product-to-invoice',
                type: 'POST',
                data: {
                    idprodus: idprodus,
                    cantitate: cantitate,
                    pret: pret,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        // मॉडल बंद करें
                        $('#myModal').modal('hide');
                        // इनवॉइस आइटम्स रिफ्रेश करें
                        loadInvoiceItems();
                    } else {
                        alert(response.message);
                    }
                },
                error: function() {
                    alert('Error adding product');
                }
            });
        }

        // इनवॉइस आइटम्स लोड करने का फंक्शन
        function loadInvoiceItems() {
            $.ajax({
                url: '/get-invoice-items',
                type: 'GET',
                success: function(data) {
                    // इनवॉइस आइटम्स टेबल अपडेट करें
                    $('#invoice-items').html(data);
                    // यदि कोई आइटम नहीं मिले तो आइटम कंटेनर छिपाएं
                    if (data.indexOf('No items found') !== -1) {
                        $('#invoice-items-container').hide();
                    } else {
                        $('#invoice-items-container').show();
                    }
                }
            });
        }

        // इनवॉइस आइटम हटाने का फंक्शन
        function removeItem(itemId) {
            if (confirm('Ești sigur că vrei să ștergi acest produs?')) {
                $.ajax({
                    url: '/remove-invoice-item/' + itemId,
                    type: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        _method: 'DELETE'
                    },
                    success: function() {
                        loadInvoiceItems();
                    }
                });
            }
        }

        // जब मॉडल दिखाई देता है तो सर्च इनिशियलाइज करें
        $(document).ready(function() {
            $('#myModal').on('shown.bs.modal', function() {
                $('#q').focus();
                load(1);
            });
            
            // एंटर की पर सर्च करें
            $('#q').keypress(function(e) {
                if (e.which == 13) {
                    load(1);
                    return false;
                }
            });
            
            // इनवॉइस सेव करने का इवेंट हैंडलर
            $('#saveInvoice').click(function() {
                // यहां आप इनवॉइस डाटा को सर्वर पर सेव करने का कोड लिख सकते हैं
                alert('Factura salvată cu succes!');
            });
        });
    </script>

<!--Testting End-->