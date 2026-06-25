<!DOCTYPE html>
<html>
<head>
    <title>{{ $savedCart->name }} Offer</title>
    <style>
        table { width: 100%; border-collapse: collapse; }
        table, th, td { border: 1px solid black; padding: 5px; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h2>Offer: {{ $savedCart->name }}</h2>
    <table>
        <thead>
            <tr>
                <th>Supplier</th>
                <th>Brand</th>
                <th>Product</th>
                <th>AM Code</th>
                <th>Price</th>
                <th>Qty</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cartItems as $supplier => $items)
                @foreach($items as $item)
                    <tr>
                        <td>{{ $item['supplier'] }}</td>
                        <td>{{ $item['manufacturer'] }}</td>
                        <td>{{ $item['product_name'] }}</td>
                        <td>{{ $item['product_code'] }}</td>
                        <td>{{ $item['price'] }} {{ $item['currency'] }}</td>
                        <td>{{ $item['qty'] }}</td>
                        <td>{{ $item['price'] * $item['qty'] }} {{ $item['currency'] }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</body>
</html>