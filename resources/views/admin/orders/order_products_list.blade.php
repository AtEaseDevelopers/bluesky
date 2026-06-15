
<div class="form-group mb-4">
    <label class="mb-2" for="do_date">DO Date</label>
    <input type="date" class="form-control" name="do_date" id="do_date" value="{{ $order['do_date'] }}">
</div>
<table class="table table-bordered mb-0">
    <thead>
        <th>Product</th>
        <th>Weight</th>
    </thead>
    <tbody>
        @foreach ($products as $key => $product)
            <tr>
                <td>{{ $product->name }}</td>
                <td>
                    <div class="input-group">
                        <input type="number" step="0.01" class="form-control" name="order_product_{{ $product->id }}" placeholder="Product Weight" min="1" value="{{ $product->weight }}">
                        <div class="input-group-text" id="order_product_{{ $product->id }}">KG</div>
                    </div>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>