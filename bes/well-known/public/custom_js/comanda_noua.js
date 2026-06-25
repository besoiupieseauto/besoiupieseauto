// सबमिट से पहले फॉर्म वैलिडेशन के लिए फंक्शन जोड़ें
$("#comanda_noua").on('submit', function(e) {
    // idmasina_cmd की वैल्यू चेक करें
    var idmasina = $('#idmasina_cmd').val();
    
    // यदि खाली है या 0 है, तो डिफॉल्ट वैल्यू सेट करें
    if (!idmasina || idmasina == '0') {
        $('#idmasina_cmd').val(1); // यहां डिफॉल्ट वैल्यू बदलें
        console.log('Empty idmasina detected on submit, setting default value');
    }
    
    // मार्का फील्ड की वैल्यू चेक करें
    var marca = $('#marca').val();
    
    // यदि मार्का भरा है, लेकिन वह रीडओनली है, तो हिडन फील्ड जोड़ें
    if (marca && marca.trim() !== '' && $('#marca').prop('readonly')) {
        $('<input>').attr({
            type: 'hidden',
            name: 'marca',
            value: marca
        }).appendTo('#comanda_noua');
        console.log('Added hidden marca field with value: ' + marca);
    }
});