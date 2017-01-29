@extends('header')

@section('content')

	{!! Former::open($url)
            ->addClass('col-md-10 col-md-offset-1 warn-on-exit')
            ->method($method)
            ->rules([
                'name' => 'required',
				'relation_id' => 'required',
            ]) !!}

    @if ($project)
        {!! Former::populate($project) !!}
    @endif

    <span style="display:none">
        {!! Former::text('public_id') !!}
    </span>

	<div class="row">
        <div class="col-md-10 col-md-offset-1">

            <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">{!! trans('texts.project') !!}</h3>
            </div>
            <div class="panel-body">

				@if ($project)
					{!! Former::plaintext('relation_name')
							->value($project->relation->getDisplayName()) !!}
				@else
					{!! Former::select('relation_id')
							->addOption('', '')
							->label(trans('texts.relation'))
							->addGroupClass('relation-select') !!}
				@endif

                {!! Former::text('name') !!}


            </div>
            </div>

        </div>
    </div>


	<center class="buttons">
        {!! Button::normal(trans('texts.cancel'))->large()->asLinkTo(url('/expense_categories'))->appendIcon(Icon::create('remove-circle')) !!}
        {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
		@if ($project && Auth::user()->can('create', ENTITY_TASK))
	    	{!! Button::primary(trans('texts.new_task'))->large()
					->asLinkTo(url("/tasks/create/{$project->relation->id}/{$project->public_id}"))
					->appendIcon(Icon::create('plus-sign')) !!}
		@endif
	</center>

	{!! Former::close() !!}

    <script>

		var relations = {!! $relations !!};

        $(function() {
			var $clientSelect = $('select#relation_id');
            for (var i=0; i<relations.length; i++) {
                var relation = relations[i];
                var clientName = getClientDisplayName(relation);
                if (!clientName) {
                    continue;
                }
                $clientSelect.append(new Option(clientName, relation.public_id));
            }
			@if ($relationPublicId)
				$clientSelect.val({{ $relationPublicId }});
			@endif

			$clientSelect.combobox();

			@if ($relationPublicId)
				$('#name').focus();
			@else
				$('.relation-select input.form-control').focus();
			@endif
        });

    </script>

@stop
