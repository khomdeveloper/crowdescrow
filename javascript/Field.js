var Field = {
    setControl : function(){
	A.w(['$','B','Client'],function(){
	   B.down($('.rocket_control'),function(obj){
	       Client.command = obj.attr('alt');
	       Client.stop(); //stop timer
	       Client.ignoreResponse = Client.observe(); //restart observer and set ignore flag
	       Units.getMyRocket().command(obj.attr('alt'));
	   }); 
	});
    }
};