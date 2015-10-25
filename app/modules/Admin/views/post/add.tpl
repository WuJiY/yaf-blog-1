<!DOCTYPE html>
<html lang="zh-CN">
<head>
	<title>苦李博客 | 后台管理</title>
	<load href="/Public/bootstrap/css/bootstrap.min.css" />
	<load href="/Public/bootstrap/css/font-awesome.min.css" />
	<load href="/Public/simditor/font-awesome.css" />
	<load href="/Public/simditor/simditor.css" />
    <load href="/Public/icheck/skins/all.css" />
	<load href="/Public/js/jquery.mCustomScrollbar.css" />
	<load href="/Public/css/article.css" />
</head>
<body>	

<div id="article" class="simditor_page container">
	<div class="main">
		<div class="form-group">
			<input n='1' type="text" name="title" class="_param form-control" p="文章标题" placeholder="文章标题">
		</div>
		<textarea n='1' id="editor" name="content" class="_param" autofocus></textarea>
		<div class="ct form-group">
			<label  class="category">
				分类：
				<i func="_modal" modal="select_category" class="icon-plus click"></i> 
			</label>
			<label class="tag">
				标签：
				<i func="_modal" modal="select_tag" class="icon-plus click"></i>
			</label>
		</div>
		<div class="a_action">
			<button type="button" class="click btn btn-warning" status="1" func="add_article">
				<i class="icon-off"></i>保存
			</button>
			<!--
			<button type="button" class="btn btn-info"><i class="icon-eye-open"></i>预览</button>
			-->
			<button type="button" class="click btn btn-success" status="0" func="add_article">
				<i class="icon-ok"></i>提交
			</button>
		</div>
	</div>
</div>

<div id="select_category" class="article_select modal fade">
	<div class="modal-dialog">
	<div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal">
				<span aria-hidden="true">&times;</span>
				<span class="sr-only">Close</span>
			</button>
			<h4 class="modal-title">选择分类</h4>
		</div>
		<div class="modal-body">
			<volist name="clist" id="vo">
				<?php if($vo['name'] == '全部') continue; ?>
				<div class="pcategory">
					<input class="icheck" type="checkbox" value="{$vo.id}">
					<label>{$vo.name}</label>
				</div>
				<ul class="ccategory">
					<volist name="vo.child" id="ao">
						<li>
							<input class="icheck" type="checkbox" value="{$ao.id}">
							<label>{$ao.name}</label>
						</li>
					</volist>
				</ul>
			</volist>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal">
				关闭
			</button>
		</div>
	</div>
	</div>
</div>

<div id="select_tag" class="article_select modal fade">
	<div class="modal-dialog">
	<div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal">
				<span aria-hidden="true">&times;</span>
				<span class="sr-only">Close</span>
			</button>
			<h4 class="modal-title">选择标签</h4>
		</div>
		<div class="modal-body">
			<ul class="ccategory">
				<volist name="tlist" id="vo">
					<li>
						<input class="icheck" type="checkbox" value="{$vo.id}">
						<label>{$vo.name}</label>
					</li>
				</volist>
			</ul>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal">
				关闭
			</button>
		</div>
	</div>
	</div>
</div>


<include file="Public:footer" />
<script type="text/javascript">
	$(document).ready(function(){
		new Simditor({
			textarea: $('#editor'),
			toolbar: ['title', 'bold', 'italic', 'underline', 'strikethrough', 'color', '|', 'ol', 'ul', 'blockquote', 'code', 'table', '|', 'link', 'image', 'hr', '|', 'indent', 'outdent']
		});
	});

</script>