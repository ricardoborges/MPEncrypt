<template>
	<NcAppContent>
		<div class="content">
			<h1>Hello world!</h1>
			<p>
				Criptografia de PDF
			</p>
		</div>
	</NcAppContent>
</template>

<script>
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'

export default {
	name: 'App',
	components: {
		NcAppContent,
	},
}
</script>

<style scoped lang="scss">
.content {
	display: flex;
	justify-content: center;
	margin: 16px;
}
</style>
