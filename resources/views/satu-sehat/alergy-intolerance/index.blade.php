<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Allergy Intolerance</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .ai-status {
            vertical-align: middle
        }

        .sselect-wrapper.form-control {
            cursor: text;
            min-height: 38px
        }
    </style>
</head>

<body>
    <div class="container-fluid py-3">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="mb-0">Allergy Intolerance</h3>
            <div class="d-flex gap-2">
                <button id="ambil-semua" type="button" class="btn btn-warning btn-sm">🔄 Ambil Semua
                    Patient/Practitioner</button>
                <button id="auto-map-semua" type="button" class="btn btn-outline-secondary btn-sm">🧠 Ambil Semua
                    Kode</button>
                <button id="kirim-semua" type="button" class="btn btn-primary btn-sm">🚀 Kirim Semua</button>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <form method="GET" class="row g-2">
                    <div class="col-12 col-md-3">
                        <label class="form-label mb-1">Dari Tanggal</label>
                        <input type="date" name="from" class="form-control form-control-sm"
                            value="{{ request('from') }}">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label mb-1">Sampai Tanggal</label>
                        <input type="date" name="to" class="form-control form-control-sm"
                            value="{{ request('to') }}">
                    </div>
                    <div class="col-12 col-md-6 d-flex justify-content-md-end align-items-end">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                            <a href="{{ route('satusehat.allergy-intolerance.index') }}"
                                class="btn btn-outline-secondary btn-sm">Reset</a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover align-middle mb-0">
                        <thead class="table-primary">
                            <tr class="text-nowrap">
                                <th>Nama Pasien</th>
                                <th>Info</th>
                                <th>Patient ID</th>
                                <th>Practitioner ID</th>
                                <th style="width:25%">Alergi (teks)</th>
                                <th style="width:25%">Kode Medication (SNOMED)</th>
                                <th style="width:12%">Category</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($regPeriksa as $r)
                                <form action="{{ route('satusehat.allergy-intolerance.store') }}" method="POST"
                                    class="row-form">
                                    @csrf
                                    <tr>
                                        <td class="pasien-nama text-wrap">
                                            <span class="patient-name">{{ $r->pasien->nm_pasien ?? '-' }}</span><br>
                                            <span class="badge bg-primary no-rawat-badge">{{ $r->no_rawat }}</span>
                                        </td>

                                        <td class="tgl-iso"
                                            data-recorded-at="{{ ($r->tgl_registrasi ?? '') && ($r->jam_reg ?? '') ? $r->tgl_registrasi . 'T' . $r->jam_reg . '+07:00' : '' }}">
                                            <span
                                                class="recorded-at">{{ ($r->tgl_registrasi ?? '') && ($r->jam_reg ?? '') ? $r->tgl_registrasi . 'T' . $r->jam_reg . '+07:00' : '-' }}</span><br>
                                            <span
                                                class="badge bg-primary encounter-badge">{{ $r->satuSehatEncounter->id_encounter ?? '-' }}</span><br>
                                            <span class="doctor-name">{{ $r->dokter->nm_dokter ?? '-' }}</span>
                                        </td>

                                        <td class="patient-id text-center text-muted">-</td>
                                        <td class="pract-id text-center text-muted">-</td>

                                        <td class="text-wrap">
                                            @php
                                                $alergiText = collect($r->pemeriksaanRalan ?? [])
                                                    ->pluck('alergi')
                                                    ->filter()
                                                    ->unique()
                                                    ->implode(', ');
                                            @endphp
                                            <input type="text" name="alergi[]"
                                                class="form-control form-control-sm alergi-input"
                                                value="{{ $alergiText }}" placeholder="mis. AMOXICILLIN">
                                        </td>

                                        <td class="sn-cell">
                                            <div class="d-flex gap-2">
                                                <input type="text"
                                                    class="form-control form-control-sm snomedct-allerged"
                                                    placeholder="Cari kode SNOMED..." autocomplete="off">
                                                <button type="button"
                                                    class="btn btn-outline-secondary btn-sm auto-map">🧠 Ambil
                                                    Kode</button>
                                            </div>
                                            <input type="hidden" name="snomed_display[]">
                                        </td>

                                        <td>
                                            <input type="text" name="category[]"
                                                class="form-control form-control-sm category-input"
                                                placeholder="medication" readonly>
                                        </td>

                                        <td>
                                            <div class="d-flex flex-wrap gap-2 justify-content-center">
                                                <button type="button" class="btn btn-info btn-sm ambil-data"
                                                    data-pasien="{{ $r->pasien->no_ktp ?? '-' }}"
                                                    data-dokter="{{ $r->dokter->pegawai->no_ktp ?? '-' }}"
                                                    data-encounter="{{ $r->satuSehatEncounter->id_encounter ?? '' }}"
                                                    data-nama="{{ $r->pasien->nm_pasien ?? 'anonymous' }}"
                                                    @if ($r->allergyIntolerance) disabled title="Sudah terkirim" @endif>
                                                    Ambil
                                                </button>

                                                <button type="button" class="btn btn-success btn-sm kirim"
                                                    @if ($r->allergyIntolerance) disabled @endif>
                                                    {{ $r->allergyIntolerance ? 'Terkirim' : 'Kirim' }}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Hidden holder untuk nilai yang dikirim -->
                                    <input type="hidden" name="patient_id">
                                    <input type="hidden" name="practitioner_id">
                                    <input type="hidden" name="encounter_uuid"
                                        value="{{ $r->satuSehatEncounter->id_encounter ?? '' }}">
                                    <input type="hidden" name="patient_name"
                                        value="{{ $r->pasien->nm_pasien ?? '' }}">
                                    <input type="hidden" name="no_rawat"
                                        value="{{ $r->no_rawat_id ?? ($r->no_rawat ?? '') }}">
                                </form>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">Tidak ada data ditemukan
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Sselect lib -->
    <script src="{{ asset('Sselect.js') }}"></script>
    <script>
        // ===== Basic util & constants
        const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const URL_SNOMED_SEARCH = @json(route('snomed-ct.allergy-intolerance'));
        const URL_AI_MAP = @json(route('ai.snomed.map'));
        const URL_STORE = @json(route('satusehat.allergy-intolerance.store'));

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': CSRF
            }
        });

        const cleanText = (s) => (s || '').replace(/\s+/g, ' ').trim();

        // ===== Status badge
        function setRowStatus($row, {
            text,
            cls
        }) {
            let $status = $row.find('.ai-status');
            if (!$status.length) {
                $status = $('<span class="ai-status ms-2 badge"></span>');
                $row.find('td:last .d-flex').append($status);
            }
            $status.removeClass('bg-secondary bg-info bg-success bg-danger')
                .addClass(cls || 'bg-secondary')
                .text(text);
        }

        // ===== Init Sselect per input (display input tanpa name; hidden yg submit)
        function initSselectFor(el) {
            el.removeAttribute('name');
            const inst = new Sselect(el, {
                name: 'snomedct_allerged[]',
                url: URL_SNOMED_SEARCH,
                searchParam: 'search',
                dataField: {
                    value: 'code',
                    label: 'display'
                },
                onSelect: (item, api) => {
                    const $row = $(api.el).closest('tr');
                    const cat = Array.isArray(item.category) ? item.category.join(', ') : (item.category ??
                        'medication');
                    $row.find('.category-input').val(cat || 'medication');
                    $row.find('input[name="snomed_display[]"]').val(item.display || '');
                }
            });
            el._sselect = inst;
        }
        $('.snomedct-allerged').each(function() {
            initSselectFor(this);
        });

        // ===== Set pilihan SNOMED secara programatik
        function setSnomedSelection($row, code, display, category = 'medication') {
            const inputEl = $row.find('.snomedct-allerged')[0];
            if (!inputEl || !inputEl._sselect) return false;
            inputEl._sselect.api().setValue({
                value: code,
                label: display
            });
            $row.find('.category-input').val(category || 'medication');
            $row.find('input[name="snomed_display[]"]').val(display || '');
            return true;
        }

        // ===== Panggil AI (POST)
        async function aiMapOne($row) {
            const raw = ($row.find('input[name="alergi[]"]').val() || '').trim();
            if (!raw) throw new Error('kosong');

            const res = await fetch(URL_AI_MAP, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': CSRF,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    alergi_text: raw
                })
            });
            if (!res.ok) throw new Error('AI HTTP ' + res.status);

            const json = await res.json();
            if (!json.success || !json.match) throw new Error(json.reason || 'no_match');

            const {
                code,
                display,
                category
            } = json.match;
            setSnomedSelection($row, code, display, category || 'medication');
            return json.match;
        }

        // ===== Kumpulkan field utk kirim ke controller store
        function collectRowFields($row, $form) {
            const noRawat = cleanText($form.find('input[name="no_rawat"]').val() || $row.find('.no-rawat-badge').text());
            const patientName = cleanText($form.find('input[name="patient_name"]').val() || $row.find('.patient-name')
            .text());
            const recordedAt = cleanText($row.find('.tgl-iso').attr('data-recorded-at') || $row.find('.recorded-at')
        .text());

            const code = cleanText(
                $row.find('input[type="hidden"][name="snomedct_allerged[]"]').last().val() ||
                $row.find('input[name="snomedct_allerged[]"]').last().val() || ''
            );
            const cat = cleanText($row.find('input[name="category[]"]').val());
            const alergi = cleanText($row.find('input[name="alergi[]"]').val());

            const patientId = cleanText($form.find('input[name="patient_id"]').val() || $row.find('.patient-id').text());
            const practId = cleanText($form.find('input[name="practitioner_id"]').val() || $row.find('.pract-id').text());
            const encounter = cleanText($form.find('input[name="encounter_uuid"]').val() || $row.find('.encounter-badge')
                .text());

            return {
                'alergi[]': alergi,
                'snomedct_allerged[]': code,
                'category[]': cat,
                patient_id: patientId,
                practitioner_id: practId,
                encounter_uuid: encounter,
                patient_name: patientName,
                recorded_at: recordedAt,
                no_rawat: noRawat
            };
        }

        // ===== Ambil Patient & Practitioner
        $(document).on('click', '.ambil-data', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            const btn = $(this);
            const row = btn.closest('tr');
            const form = btn.closest('form');

            const pasienKtp = btn.data('pasien');
            const dokterKtp = btn.data('dokter');
            const encounter = btn.data('encounter');
            const nama = btn.data('nama');

            const patientCell = row.find('.patient-id');
            const practCell = row.find('.pract-id');

            btn.prop('disabled', true).text('Ambil...');
            try {
                const patient = await $.getJSON(`/satusehat/patient/${pasienKtp}`);
                const practitioner = await $.getJSON(`/satusehat/practitioner/${dokterKtp}`);

                const patientId = patient.id ?? patient.entry?.[0]?.resource?.id ?? '-';
                const practId = practitioner.id ?? practitioner.entry?.[0]?.resource?.id ?? '-';

                patientCell.text(patientId).removeClass('text-muted');
                practCell.text(practId).removeClass('text-muted');

                form.find('input[name="patient_id"]').val(patientId);
                form.find('input[name="practitioner_id"]').val(practId);
                form.find('input[name="encounter_uuid"]').val(encounter || '');
                form.find('input[name="patient_name"]').val(cleanText(nama || row.find('.patient-name')
            .text()));

                btn.text('Diambil').removeClass('btn-info').addClass('btn-success');
                form.find('.kirim').prop('disabled', false);

                // opsional: langsung AI map
                try {
                    await aiMapOne(row);
                    setRowStatus(row, {
                        text: 'Kode dipilih',
                        cls: 'bg-info'
                    });
                } catch (_) {}
            } catch (_) {
                alert('⚠️ Gagal mengambil data Patient/Practitioner.');
                btn.prop('disabled', false).text('Ambil');
            }
        });

        // ===== Tanda sudah terkirim (server-side disable)
        $('.kirim:disabled').each(function() {
            const row = $(this).closest('tr');
            row.find('.ambil-data').prop('disabled', true).removeClass('btn-info').addClass('btn-outline-secondary')
                .text('Terkirim');
            row.find('.patient-id, .pract-id').addClass('text-muted');
            setRowStatus(row, {
                text: 'Terkirim',
                cls: 'bg-success'
            });
        });

        // ===== 🧠 Ambil Kode (per baris)
        $(document).on('click', '.auto-map', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $btn = $(this),
                $row = $btn.closest('tr');
            $btn.prop('disabled', true).text('Mencari…');
            try {
                const m = await aiMapOne($row);
                $btn.text('Terpilih').removeClass('btn-outline-secondary').addClass('btn-success');
                setRowStatus($row, {
                    text: `Kode ${m.code}`,
                    cls: 'bg-info'
                });
            } catch (_) {
                $btn.prop('disabled', false).text('🧠 Ambil Kode');
                alert('Tidak ditemukan kandidat yang meyakinkan.');
            }
        });

        // ===== 🧠 Ambil Semua Kode
        $('#auto-map-semua').on('click', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $btn = $(this),
                $rows = $('tbody tr');
            if (!$rows.length) return alert('Tidak ada data.');
            $btn.prop('disabled', true).text('🧠 Memetakan semua…');
            let ok = 0,
                fail = 0;
            for (let i = 0; i < $rows.length; i++) {
                const $r = $($rows[i]);
                try {
                    await aiMapOne($r);
                    ok++;
                    setRowStatus($r, {
                        text: 'Kode dipilih',
                        cls: 'bg-info'
                    });
                } catch {
                    fail++;
                }
                await new Promise(r => setTimeout(r, 120));
            }
            $btn.text(`🧠 Selesai: ${ok} dipilih, ${fail} gagal`)
                .toggleClass('btn-success', fail === 0)
                .toggleClass('btn-warning', fail > 0)
                .prop('disabled', false);
        });

        // ===== Kirim satu baris
        $(document).on('click', '.kirim', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            const btn = $(this),
                row = btn.closest('tr'),
                form = btn.closest('form');
            const fields = collectRowFields(row, form);
            if (!fields['snomedct_allerged[]']) return alert('Pilih/otomatisasi kode SNOMED dulu.');
            if (!fields.patient_id || !fields.practitioner_id) return alert(
                'Ambil data Patient & Practitioner dulu.');

            btn.prop('disabled', true).text('Mengirim...');
            form.find('.ambil-data').prop('disabled', true);
            setRowStatus(row, {
                text: 'Mengirim…',
                cls: 'bg-info'
            });

            try {
                await $.ajax({
                    url: URL_STORE,
                    method: 'POST',
                    data: fields,
                    dataType: 'json'
                });
                btn.text('Terkirim').removeClass('btn-success').addClass('btn-outline-secondary');
                setRowStatus(row, {
                    text: 'Terkirim',
                    cls: 'bg-success'
                });
            } catch (xhr) {
                btn.prop('disabled', false).text('Kirim');
                form.find('.ambil-data').prop('disabled', false);
                setRowStatus(row, {
                    text: 'Gagal',
                    cls: 'bg-danger'
                });
                alert('❌ ' + (xhr?.responseJSON?.message || 'Gagal mengirim.'));
            }
        });

        // ===== Kirim semua
        $('#kirim-semua').on('click', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $btn = $(this);
            const forms = $('.row-form').filter(function() {
                const f = $(this),
                    row = f.find('tr'),
                    fields = collectRowFields(row, f);
                return fields['snomedct_allerged[]'] && fields.patient_id && fields.practitioner_id && !
                    row.find('.kirim').prop('disabled');
            });
            if (!forms.length) return alert('Tidak ada baris siap dikirim.');

            $btn.prop('disabled', true).text('🚀 Mengirim Semua...');
            let sukses = 0,
                gagal = 0;
            for (let i = 0; i < forms.length; i++) {
                const f = $(forms[i]),
                    row = f.find('tr'),
                    btn = row.find('.kirim'),
                    fields = collectRowFields(row, f);
                btn.prop('disabled', true).text('Mengirim...');
                f.find('.ambil-data').prop('disabled', true);
                setRowStatus(row, {
                    text: 'Mengirim…',
                    cls: 'bg-info'
                });
                try {
                    await $.ajax({
                        url: URL_STORE,
                        method: 'POST',
                        data: fields,
                        dataType: 'json'
                    });
                    btn.text('Terkirim').removeClass('btn-success').addClass('btn-outline-secondary');
                    setRowStatus(row, {
                        text: 'Terkirim',
                        cls: 'bg-success'
                    });
                    sukses++;
                } catch {
                    btn.prop('disabled', false).text('Kirim');
                    f.find('.ambil-data').prop('disabled', false);
                    setRowStatus(row, {
                        text: 'Gagal',
                        cls: 'bg-danger'
                    });
                    gagal++;
                }
                await new Promise(r => setTimeout(r, 200));
            }
            $btn.text(`Selesai: ${sukses} sukses, ${gagal} gagal`).removeClass('btn-primary').addClass(gagal ?
                'btn-warning' : 'btn-success').prop('disabled', false);
        });

        // ===== Ambil semua patient/practitioner
        $('#ambil-semua').on('click', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            const btnAll = $(this),
                rows = $('.ambil-data');
            if (!rows.length) return alert('Tidak ada data.');
            btnAll.prop('disabled', true).text('🔄 Mengambil...');
            for (let i = 0; i < rows.length; i++) {
                const btn = $(rows[i]);
                if (btn.prop('disabled')) continue;
                btn.trigger('click');
                await new Promise(r => setTimeout(r, 180));
            }
            btnAll.text('✅ Semua Diambil').removeClass('btn-warning').addClass('btn-success').prop('disabled',
                false);
        });
    </script>
</body>

</html>
