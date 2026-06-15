<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header d-flex align-content-center flex-wrap gap-2">
                <div>
                    <h5 class="modal-title" id="addProductModalLabel">Add Product</h5>
                    <p class="mx-auto text-muted">Please select a product you would like to add into the bag!</p>
                </div>
                <div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="product-search" placeholder="Enter product name or sku">
                    <button class="btn bg-transparent border">
                        <i class="fa fa-search"></i>
                    </button>
                </div>
                <div id="productList"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="background-color: lightblue;" id="select-all">Select All</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button>
                <button type="button" class="btn btn-primary" id="add-products">Add Products</button>
            </div>
        </div>
    </div>
</div>