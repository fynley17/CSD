<template>
    <div class="card border-0 rounded-0 h-100">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="h5 fw-bold mb-0">Recent Tests</h2>
            <a href="#" class="small text-dark">Active Tests</a>
        </div>

        <div class="px-3 px-md-4 pb-4">
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Test Name</th>
                            <th scope="col">Subject</th>
                            <th scope="col">Questions</th>
                            <th scope="col">Time Limit</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="6" class="text-center text-secondary py-4">No recent tests to display.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
