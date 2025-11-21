<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Questionnaire Response</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>

<body class="bg-light py-4">
    <div class="container">
        <h3 class="mb-4 text-center">Questionnaire Response</h3>

        <form method="GET" class="row g-2 align-items-end mb-4">
            <div class="col-md-4">
                <label class="form-label">Dari Tanggal</label>
                <input type="date" name="from" class="form-control" value="{{ request('from') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Sampai Tanggal</label>
                <input type="date" name="to" class="form-control" value="{{ request('to') }}">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary mt-auto w-100">Filter</button>
                <a href="{{ route('satusehat.questioner-response.index') }}" class="btn btn-secondary mt-auto">Reset</a>
            </div>
        </form>

        <!-- 🔹 Tombol Ambil Semua Data -->
        <div class="mb-3 d-flex justify-content-between">
            <button id="ambil-semua" class="btn btn-warning btn-sm">
                🔄 Ambil Semua Data
            </button>
            <button id="kirim-semua" class="btn btn-primary btn-sm">
                🚀 Kirim Semua
            </button>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead class="table-primary">
                        <tr>
                            <th>No Rawat</th>
                            <th>Nama Pasien</th>
                            <th>Tgl & Jam</th>
                            <th>No KTP Pasien</th>
                            <th>Kode Dokter</th>
                            <th>No KTP Dokter</th>
                            <th>Encounter UUID</th>
                            <th>Patient ID</th>
                            <th>Practitioner ID</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($regPeriksa as $r)
                            <tr>
                                <td>{{ $r->no_rawat }}</td>
                                <td>{{ $r->pasien->nm_pasien ?? '-' }}</td>
                                <td>{{ $r->tgl_registrasi . 'T' . $r->jam_reg . '+07:00' ?? '-' }}</td>
                                <td>{{ $r->pasien->no_ktp ?? '-' }}</td>
                                <td>{{ $r->dokter->nm_dokter ?? '-' }}</td>
                                <td>{{ $r->dokter->pegawai->no_ktp ?? '-' }}</td>
                                <td>{{ $r->satuSehatEncounter->id_encounter ?? '-' }}</td>

                                <!-- Kolom untuk menampilkan ID yang diambil -->
                                <td class="patient-id text-center text-muted">-</td>
                                <td class="pract-id text-center text-muted">-</td>

                                <td>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-info ambil-data"
                                            data-pasien="{{ $r->pasien->no_ktp ?? '-' }}"
                                            data-dokter="{{ $r->dokter->pegawai->no_ktp ?? '-' }}"
                                            data-encounter="{{ $r->satuSehatEncounter->id_encounter ?? '' }}"
                                            data-nama="{{ $r->pasien->nm_pasien ?? 'anonymous' }}"
                                            @if ($r->questionnaireResponse) disabled title="Sudah terkirim" @endif>
                                            Ambil Data
                                        </button>


                                        <!-- Tombol Kirim -->
                                        <button class="btn btn-sm btn-success kirim"
                                            data-tgl="{{ $r->tgl_registrasi }}" data-jam="{{ $r->jam_reg }}"
                                            @if ($r->questionnaireResponse) disabled @endif>
                                            {{ $r->questionnaireResponse ? 'Sudah Dikirim' : 'Kirim' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-3">Tidak ada data ditemukan</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        $(function() {
            // 🔹 Tombol Ambil Data
            $('.ambil-data').on('click', async function() {
                const btn = $(this);
                const pasienKtp = btn.data('pasien');
                const dokterKtp = btn.data('dokter');
                const encounter = btn.data('encounter');
                const nama = btn.data('nama');

                const row = btn.closest('tr');
                const patientCell = row.find('.patient-id');
                const practCell = row.find('.pract-id');
                const kirimBtn = row.find('.kirim');

                btn.text('Mengambil...').prop('disabled', true);

                try {
                    const patient = await $.getJSON(`/satusehat/patient/${pasienKtp}`);
                    const practitioner = await $.getJSON(`/satusehat/practitioner/${dokterKtp}`);

                    const patientId = patient.id ?? patient.entry?.[0]?.resource?.id ?? '-';
                    const practId = practitioner.id ?? practitioner.entry?.[0]?.resource?.id ?? '-';

                    patientCell.text(patientId).removeClass('text-muted');
                    practCell.text(practId).removeClass('text-muted');

                    btn.text('Data Diambil').removeClass('btn-info').addClass('btn-success');
                    kirimBtn.prop('disabled', false)
                        .data('patient-id', patientId)
                        .data('pract-id', practId)
                        .data('encounter', encounter)
                        .data('nama', nama);
                } catch (err) {
                    alert('⚠️ Gagal mengambil data Patient/Practitioner.');
                    btn.text('Ambil Data').prop('disabled', false);
                }
            });

            // 🔹 Ambil Semua Data
            $('#ambil-semua').on('click', async function() {
                const btnAll = $(this);
                const rows = $('.ambil-data');
                if (rows.length === 0) return alert('Tidak ada data.');

                btnAll.text('Mengambil Semua...').prop('disabled', true);

                for (let i = 0; i < rows.length; i++) {
                    const btn = $(rows[i]);
                    if (btn.prop('disabled')) continue;
                    await btn.trigger('click');
                    await new Promise(r => setTimeout(r, 400));
                }

                btnAll.text('✅ Semua Data Diambil')
                    .removeClass('btn-warning').addClass('btn-success');
            });

            // Sinkronisasi: jika tombol Kirim disabled (sudah terkirim), matikan Ambil Data juga
            $('.kirim:disabled').each(function() {
                const row = $(this).closest('tr');
                const ambilBtn = row.find('.ambil-data');
                ambilBtn.prop('disabled', true)
                    .removeClass('btn-info')
                    .addClass('btn-outline-secondary')
                    .text('Terkirim');
                // opsional: tandai kolom ID agar jelas
                row.find('.patient-id, .pract-id').addClass('text-muted');
            });


            // 🔹 Kirim Satu Baris
            // 🔹 Kirim Satu Baris (POST klasik, no AJAX)
            $('.kirim').on('click', function() {
                const btn = $(this);
                const row = btn.closest('tr');

                const patientId = btn.data('patient-id');
                const practId = btn.data('pract-id');
                const encounter = btn.data('encounter');
                const nama = btn.data('nama');
                const tgl = btn.data('tgl');
                const jam = btn.data('jam');

                // ambil no_rawat dari kolom pertama baris (tanpa menambah atribut baru)
                const noRawat = row.children().eq(0).text().trim();

                if (!patientId || !practId) {
                    alert('Ambil data dulu sebelum kirim!');
                    return;
                }
                if (btn.prop('disabled')) {
                    alert('Data ini sudah dikirim.');
                    return;
                }

                const authored = `${tgl}T${jam}+07:00`;
                const csrf = $('meta[name="csrf-token"]').attr('content');
                const action = "{{ route('satusehat.questioner-response.store') }}";

                // buat form sementara dan submit
                const $form = $('<form>', {
                    method: 'POST',
                    action,
                    style: 'display:none'
                });
                $form.append($('<input>', {
                    type: 'hidden',
                    name: '_token',
                    value: csrf
                }));
                $form.append($('<input>', {
                    type: 'text',
                    name: 'no_rawat',
                    value: noRawat,
                    readonly: true
                }));
                $form.append($('<input>', {
                    type: 'text',
                    name: 'patient_id',
                    value: patientId,
                    readonly: true
                }));
                $form.append($('<input>', {
                    type: 'text',
                    name: 'practitioner_id',
                    value: practId,
                    readonly: true
                }));
                $form.append($('<input>', {
                    type: 'text',
                    name: 'encounter_uuid',
                    value: encounter,
                    readonly: true
                }));
                $form.append($('<input>', {
                    type: 'text',
                    name: 'patient_name',
                    value: nama,
                    readonly: true
                }));
                $form.append($('<input>', {
                    type: 'text',
                    name: 'authored_date',
                    value: authored,
                    readonly: true
                }));

                $('body').append($form);
                btn.prop('disabled', true).text('Mengirim...');
                $form.trigger('submit');
            });


            // 🔹 Kirim Semua
            // 🔹 Kirim Semua (POST klasik via iframe tersembunyi)
            $('#kirim-semua').on('click', async function() {
                const action = "{{ route('satusehat.questioner-response.store') }}";
                const btnAll = $(this);
                const rowsKirim = $('.kirim').filter(function() {
                    const b = $(this);
                    // hanya yang belum dikirim & sudah punya patient/pract
                    return !b.prop('disabled') && b.data('patient-id') && b.data('pract-id');
                });

                if (rowsKirim.length === 0) {
                    alert('Tidak ada data yang bisa dikirim. Pastikan sudah klik "Ambil Data".');
                    return;
                }

                btnAll.prop('disabled', true).text('🚀 Mengirim Semua...');

                let terkirim = 0;

                for (let i = 0; i < rowsKirim.length; i++) {
                    const btn = $(rowsKirim[i]);
                    const row = btn.closest('tr');

                    const payload = {
                        no_rawat: row.children().eq(0).text().trim(),
                        patient_id: btn.data('patient-id'),
                        practitioner_id: btn.data('pract-id'),
                        encounter_uuid: btn.data('encounter'),
                        patient_name: btn.data('nama'),
                        authored_date: `${btn.data('tgl')}T${btn.data('jam')}+07:00`,
                    };

                    // POST klasik ke store lewat iframe (tidak mengganggu halaman utama)
                    postKlasik(action, payload, 'qr_hidden_iframe');

                    // UX: tandai baris sebagai "Mengirim..." lalu "Terkirim" (optimistic)
                    btn.text('Mengirim...').prop('disabled', true);
                    setTimeout(() => {
                        btn.text(
                            'Sudah Dikirim'
                        ); // kamu boleh ubah ke cek real dari server jika mau
                        terkirim++;
                    }, 400);

                    // throttle tipis biar server tidak ke-spam
                    await new Promise(r => setTimeout(r, 350));
                }

                setTimeout(() => {
                    btnAll.text(`✅ Terkirim ${terkirim}/${rowsKirim.length}`).removeClass(
                        'btn-primary').addClass('btn-success');
                    // kalau mau refresh halaman setelah batch:
                    // location.reload();
                }, 600);
            });

        });

        function postKlasik(action, fieldsObj, targetName = null) {
            const csrf = $('meta[name="csrf-token"]').attr('content');
            const $form = $('<form>', {
                method: 'POST',
                action,
                style: 'display:none',
                target: targetName || ''
            });

            // CSRF
            $form.append($('<input>', {
                type: 'hidden',
                name: '_token',
                value: csrf
            }));

            // Field kiriman (tanpa type="hidden" jika kamu ingin pure text readonly — tapi disembunyikan via style)
            Object.entries(fieldsObj).forEach(([name, value]) => {
                $form.append($('<input>', {
                    type: 'text',
                    name,
                    value,
                    readonly: true,
                    style: 'width:0;height:0;border:0;padding:0;margin:0;'
                }));
            });

            $('body').append($form);
            $form.trigger('submit');
            // optional: bersihkan setelah submit
            setTimeout(() => $form.remove(), 1000);
        }
    </script>
    <iframe name="qr_hidden_iframe" style="display:none;width:0;height:0;border:0;"></iframe>

</body>

</html>
